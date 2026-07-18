<?php
class MoodleScraper {
    private $baseUrl   = 'https://moodle.alaqsa.edu.ps';
    private $cookieFile;
    private $loggedIn  = false;
    private $sesskey   = '';

    public function __construct($cookieFile) {
        $this->cookieFile = str_replace('/', DIRECTORY_SEPARATOR, $cookieFile);
        $dir = dirname($this->cookieFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($this->cookieFile, "# Netscape HTTP Cookie File\n");
    }

    private function request($url, $post = null) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    private function ajax($payload) {
        $ch = curl_init($this->baseUrl . '/lib/ajax/service.php?sesskey=' . $this->sesskey);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    private function writeCookieToFile($header) {
        $line  = trim(substr($header, strlen('Set-Cookie:')));
        $parts = array_map('trim', explode(';', $line));
        $nv    = explode('=', array_shift($parts), 2);
        if (count($nv) < 2) return;

        $name = trim($nv[0]); $value = trim($nv[1]);
        $domain = 'moodle.alaqsa.edu.ps'; $path = '/';
        $expires = 0; $secure = 'FALSE';

        foreach ($parts as $p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
            $k = strtolower(trim($k));
            if ($k === 'domain')  $domain  = trim($v);
            if ($k === 'path')    $path    = trim($v);
            if ($k === 'expires') $expires = strtotime(trim($v)) ?: 0;
            if ($k === 'secure')  $secure  = 'TRUE';
        }

        if ($expires > 0 && $expires < time()) return;

        file_put_contents($this->cookieFile,
            implode("\t", [$domain, 'FALSE', $path, $secure, $expires ?: '0', $name, $value]) . "\n",
            FILE_APPEND
        );
    }

    public function login($username, $password) {
        $html = $this->request($this->baseUrl . '/login/index.php');

        if (!preg_match('/<input[^>]+name="logintoken"[^>]+value="([^"]+)"/i', $html, $m) &&
            !preg_match('/<input[^>]+value="([^"]+)"[^>]+name="logintoken"/i', $html, $m)) {
            return false;
        }

        $responseHeaders = [];
        $ch = curl_init($this->baseUrl . '/login/index.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'anchor' => '', 'logintoken' => $m[1],
                'username' => $username, 'password' => $password,
            ]),
            CURLOPT_REFERER        => $this->baseUrl . '/login/index.php',
            CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$responseHeaders) {
                $responseHeaders[] = $header;
                return strlen($header);
            },
        ]);
        curl_exec($ch);
        curl_close($ch);

        $redirectUrl = '';
        foreach ($responseHeaders as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) $this->writeCookieToFile($h);
            if (stripos($h, 'Location:') === 0)   $redirectUrl = trim(substr($h, 9));
        }

        if (!$redirectUrl) return false;
        if (!preg_match('/^https?:\/\//i', $redirectUrl)) $redirectUrl = $this->baseUrl . $redirectUrl;

        $html = $this->request($redirectUrl);
        $this->loggedIn = strpos($html, 'loginerrormessage') === false
                       && strpos($html, 'page-login-index') === false;

        return $this->loggedIn;
    }

    public function getCourses() {
        $html = $this->request($this->baseUrl . '/my/courses.php');
        preg_match('/"sesskey":"([^"]+)"/', $html, $sk);
        $this->sesskey = $sk[1] ?? '';
        if (!$this->sesskey) return [];

        $response = $this->ajax(json_encode([[
            'index'      => 0,
            'methodname' => 'core_course_get_enrolled_courses_by_timeline_classification',
            'args'       => [
                'offset' => 0, 'limit' => 0, 'classification' => 'inprogress',
                'sort' => 'fullname', 'customfieldname' => '', 'customfieldvalue' => '',
            ],
        ]]));

        $data = json_decode($response, true);
        $courses = [];
        foreach ($data[0]['data']['courses'] ?? [] as $c) {
            $courses[] = [
                'id'   => $c['id'],
                'name' => $c['fullname'],
                'url'  => $this->baseUrl . '/course/view.php?id=' . $c['id'],
            ];
        }
        return $courses;
    }

    public function getAllCoursesCount() {
        $response = $this->ajax(json_encode([[
            'index'      => 0,
            'methodname' => 'core_course_get_enrolled_courses_by_timeline_classification',
            'args'       => [
                'offset' => 0, 'limit' => 0, 'classification' => 'all',
                'sort' => 'fullname', 'customfieldname' => '', 'customfieldvalue' => '',
            ],
        ]]));
        $data = json_decode($response, true);
        return count($data[0]['data']['courses'] ?? []);
    }

    public function getCourseContent($courseId) {
        $html = $this->request($this->baseUrl . '/course/view.php?id=' . $courseId);
        if (empty($html)) return [];

        $typeMap = ['assign' => 'assignment', 'quiz' => 'quiz', 'forum' => 'announcement'];
        $items = []; $seen = [];

        preg_match_all(
            '/class="[^"]*modtype_(\w+)[^"]*"[^>]*>.*?href="([^"]+)"[^>]*>.*?class="[^"]*instancename[^"]*"[^>]*>\s*([^<]+)/is',
            $html, $matches, PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $modname = strtolower(trim($m[1]));
            $url     = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            $title   = trim(preg_replace('/\s+/', ' ', strip_tags($m[3])));
            if (empty($title)) continue;

            preg_match('/[?&]id=(\d+)/', $url, $idM);
            $id = $modname . '_' . ($idM[1] ?? md5($url));
            if (isset($seen[$id])) continue;
            $seen[$id] = true;

            $items[] = [
                'id'    => $id,
                'type'  => $typeMap[$modname] ?? 'lecture',
                'title' => $title,
                'url'   => $url,
            ];
        }

        return $items;
    }

    public function isLoggedIn() { return $this->loggedIn; }
}
