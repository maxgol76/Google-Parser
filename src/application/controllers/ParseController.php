<?php

namespace application\controllers;

use application\models\ParseModel;
use application\core;
use React;

class ParseController
{
    protected $model;
    private $proxyPointer = 0;

    public function __construct()
    {
        $this->model = new ParseModel();
    }

    public function getProxy()
    {
        if (isset($_SERVER['argv'][1])) {
            echo "Start getProxy Thread: " . $_SERVER['argv'][1] . "\n";

            $sec = $_SERVER['argv'][1] * 60;
            echo "Waiting\n";
            sleep(10 + $sec);
        }

        echo "Getting proxies:\n";
        //$this->model->delProxies();

        while ($this->getNewProxy()[0] <= 4000) {
            $this->getProxyFromSite2();
            echo "\nWaiting\n";
            sleep(90);
        }
    }

    public function parser()   // дочерний процесс
    {
        if (isset($_SERVER['argv'][1])) {
            $nThread = $_SERVER['argv'][1];
            echo "Start thread: N" . $nThread . "\n";
        }

        $infMan = [];
        //$n = 0;
        $limit = 20;
        $dataPeople = $this->model->getPortionNamesPeople($limit); // берёт только те имена, инфа о которых не лежит в базе

        while (count($dataPeople) != 0) { // пока есть ещё людишки - циклим
            foreach ($dataPeople as $man) {
                //if ($n++ == 20) break;  // ограничение по 20-ти человекам,
                $arrInf = $this->googleParser($man);

                if (!empty($arrInf['urls'])) {
                    for ($i = 0; $i < count($arrInf['urls']); $i++) {
                        $infMan[$i]['url'] = $arrInf['urls'][$i];
                        $infMan[$i]['title'] = $arrInf['titles'][$i];
                        $infMan[$i]['snippet'] = $arrInf['snippets'][$i];
                        $infMan[$i]['name_id'] = $arrInf['name_id'];
                    }
                    $this->model->saveInfoAboutPeople($infMan);
                }

            }
            sleep(1);
            $dataPeople = $this->model->getPortionNamesPeople($limit);
        }
        /*--------------*/
    }

    /*private function getPortionPeople()
    {
        $limit = 20;
        $dataPeople = $this->model->getPortionNamesPeople($limit); // выводит только те имена, инфа о которых не лежит в базе

        if
        return  $dataPeople;
    }*/

    public function getProxyFromSite2()
    {
        $url = 'http://gimmeproxy.com/api/getProxy';
        $countProxies = 0;
        $num = 20;
        //echo "Load {$num} proxies\n";
        for ($i = 0; $i < $num; $i++) {
            $data = json_decode(file_get_contents($url), 1);

            if ($data['supportsHttps']) {
                $proxy = $data['ipPort'];
                $this->model->saveProxy($proxy);
                $this->model->setAliveProxy($proxy);
                $countProxies++;
                $format = "Proxies from site '%s': %d\r";
                printf($format, $url, $countProxies);
            }
        }
        echo "\n";
    }


    function googleParser($man)
    {
        $fName = $man['first'];
        $lName = $man['last'];
        $id = $man['id'];

        $final_array = [];
        $final_array['name_id'] = $id;

        $num = 20; //количество результатов поисковой выдачи
        $url = 'https://www.google.com/search?q=' . $fName . '+' . $lName . '&num=' . $num;

        $try = true;
        while ($try) {

            $proxy = $this->getNewProxy();
            if (!$proxy) { // если статусы живых проксей в базе все сброшены, а новые прокси ещё не пришли - устанавливаем статусы всех проксей опять в единицу и работаем с ними.
                $this->model->setAliveAllProxies();
                $proxy = '109.185.180.87:8080';
            }

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");    //"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1"

            curl_setopt($ch, CURLOPT_REFERER, "http://www.google.com/");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            curl_setopt($ch, CURLOPT_POST, 0);

            $content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Получаем HTTP-код

            //if ($http_code == 200) {
            $html = str_get_html($content);
            //}

            //echo curl_error($ch);
            curl_close($ch);

            if ($http_code != 200) {
                $state = "Bad\n";
            } else {
                $state = "Good\n";
            }

            if (isset($_SERVER['argv'][1])) {
                $format = "Thread N" . $_SERVER['argv'][1] . ": proxy '%s': %s";
            } else {
                $format = "Proxy '%s': %s";
            }

            printf($format, $proxy, $state);

            if (!empty($html)) {
                foreach ($html->find('.g') as $element) {     //url

                    if (isset($element->find('cite', 0)->plaintext, $element->find('.r a', 0)->plaintext, $element->find('.st', 0)->plaintext)) {  // только при наличии трёх объектов
                        $url = $element->find('cite', 0)->plaintext;
                        $title = $element->find('.r a', 0)->plaintext;
                        $snippet = $element->find('.st', 0)->plaintext;

                        if (stristr($title, $fName) || stristr($snippet, $fName) || stristr($title, $lName) || stristr($snippet, $lName)) { // проверка на наличие имени в заголовке или сниппете
                            $final_array['urls'][] = $url;
                            $final_array['titles'][] = $title;
                            $final_array['snippets'][] = $snippet;

                            $format = "Parsing name: '%s %s': Ok.\n";
                            printf($format, $fName, $lName);
                            $try = 0;
                        }
                    }
                }
                echo "Left people: " . $this->model->getCountPeople() . "\n"; // получаем количество людей, инфа о которых не лежит ещё в базе
            }

            if ($try != 0) $this->model->resetAliveProxy($proxy); // если попытка с текущим прокси неудачна - помечаем что прокси не живой.
        }

        unset($html);
        return $final_array;
    }

    public function multiThreadParser()
    {
        echo "Start multi thread parser: ";

        $numThread = 20;  // число потоков
        $numThread++; // и ещё один поток будет только собирать прокси раз в период.
        echo "Number threads: $numThread\n";

        $loop = React\EventLoop\Factory::create();

        $arrProcess = [];
        for ($i = 0; $i < $numThread; $i++) {
            if ($i != 0) {            // номер потока передаем скрипту через аргумент
                $arrProcess[$i] = new React\ChildProcess\Process('php parser.php ' . $i);
            } else {
                $arrProcess[$i] = new React\ChildProcess\Process('php getproxy.php ');
            }

            $process = $arrProcess[$i];
            $loop->addTimer(0.001, function ($timer) use ($process) {
                $process->start($timer->getLoop());

                $process->stdout->on('data', function ($output) {
                    echo "Child: {$output}";
                });
            });
        }

        $loop->run();
    }

    public function multiThreadGetProxy()
    {
        echo "Start multi thread Get Proxy:\n";

        if (isset($_SERVER['argv'][1])) {
            $numThread = $_SERVER['argv'][1];
            echo "Number process: " . $_SERVER['argv'][1] . "\n";
        } else {
            $numThread = 2;
        }

        $loop = React\EventLoop\Factory::create();

        $arrProcess = [];
        for ($i = 0; $i < $numThread; $i++) {    // номер потока передаем скрипту через аргумент
            $arrProcess[$i] = new React\ChildProcess\Process('php getproxy.php ' . $i);

            $process = $arrProcess[$i];
            $loop->addTimer(0.001, function ($timer) use ($process) {
                $process->start($timer->getLoop());

                $process->stdout->on('data', function ($output) {
                    echo "Child: {$output}";
                    //echo "Child script says: {$output}";
                });
            });
        }

        $loop->run();
    }

    public function getNewProxy()
    {
        $proxyArr = $this->model->getAliveProxies();

        $count = count($proxyArr);

        $this->proxyPointer = rand(0, $count - 1);

        if ($count != 0) {
            return $proxyArr[$this->proxyPointer]['proxy'];
        } else {
            return false;
        }
    }

    public function getProxyFromFile()
    {
        $proxies = file("proxy.txt");
        foreach ($proxies as $proxy) {
            $this->model->saveProxy(trim($proxy));
        }

        $format = "Get proxies from file 'proxy.txt': %d\n";
        printf($format, count($proxies));
    }


    public function proxyChecker()
    {
        $this->model->resetLivingProxies();
        $proxies = $this->model->getProxies();

        $countAliveProxies = 0;
        foreach ($proxies as $proxy) {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "http://google.com");
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_PROXYTYPE, 0);

            curl_setopt($ch, CURLOPT_PROXY, $proxy['proxy']);
            curl_setopt($ch, CURLOPT_POST, 0);

            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Получаем HTTP-код

            echo curl_error($ch);
            curl_close($ch);

            echo "Proxy: " . $proxy['proxy'] . " HTTP:$http_code\n";

            if ($http_code == 200) {
                $this->model->setAliveProxy($proxy['proxy']);
                $countAliveProxies++;
                $format = "Alive proxies: %d\r";
                printf($format, $countAliveProxies);
            }
        }

        echo "\n\n";
    }

    public function getProxyFromSite($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");    //"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1"

        curl_setopt($ch, CURLOPT_REFERER, "http://www.google.com/");
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_POST, 0);

        $content = curl_exec($ch);
        $html = str_get_html($content);

        echo curl_error($ch);
        curl_close($ch);
        $countProxies = 0;

        if (!empty($html)) {

            foreach ($html->find('tr') as $element) {

                if (isset($element->find('td', 0)->plaintext) && isset($element->find('td', 1)->plaintext)) {
                    $adr = $element->find('td', 0)->plaintext;
                    $port = $element->find('td', 1)->plaintext;
                    $proxy = trim($adr) . ':' . trim($port);
                    //echo $proxy . "\n";
                    $this->model->saveProxy($proxy);
                    $this->model->setAliveProxy($proxy);

                    $countProxies++;
                    $format = "Proxies from site '%s': %d\r";
                    printf($format, $url, $countProxies);
                }
            }

            echo "\n";
        }
    }

    public function getProxyFromSite1()
    {
        $numPages = 4;
        $countProxies = 0;

        for ($i = 0; $i < $numPages; $i++) {

            $url = 'http://www.getproxy.jp/default/' . $i;
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");    //"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1"

            curl_setopt($ch, CURLOPT_REFERER, "http://www.google.com/");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 0);

            $content = curl_exec($ch);
            $html = str_get_html($content);

            echo curl_error($ch);
            curl_close($ch);

            if (!empty($html)) {

                foreach ($html->find('tr') as $element) {     //url

                    if (isset($element->find('td', 0)->plaintext)) {
                        $adr = $element->find('td', 0)->plaintext;

                        $proxy = trim($adr);
                        $this->model->saveProxy($proxy);
                        $this->model->setAliveProxy($proxy);
                        $countProxies++;
                        $format = "Proxies from site '%s': %d\r";
                        printf($format, $url, $countProxies);
                    }
                }
                echo "\n";
            }
        }
        echo "\n";
    }

}
