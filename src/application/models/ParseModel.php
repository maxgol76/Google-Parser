<?php

namespace application\models;

use application\core\Connect;

class ParseModel //extends Model
{
    protected $db;

    public function __construct()
    {
        Connect::execute();
        $this->db = Connect::$db;
    }

    public function showList()
    {
        $sql = "SELECT * FROM users";

        $sth = $this->db->prepare($sql);
        $sth->execute();

        $data = $sth->fetchAll();
        return $data;
    }


    public function getResult()
    {
        $sql = "SELECT * FROM result";

        $sth = $this->db->prepare($sql);
        $sth->execute();

        $data = $sth->fetchAll();
        return $data;
    }

    public function getNamesPeople()
    {
        $sql = "SELECT first, last, names.id FROM names LEFT JOIN result ON result.name_id=names.id WHERE result.name_id IS NULL";

        $sth = $this->db->prepare($sql);
        $sth->execute();

        $data = $sth->fetchAll();
        return $data;
    }

    public function getPortionNamesPeople($limit)
    {
        $sql = "LOCK TABLES names WRITE";
        $this->db->exec($sql);

        $sql = "SELECT first, last, id FROM names WHERE working = 0 AND success = 0 LIMIT ".$limit;
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();

        $sql = "UPDATE names SET working = 1 WHERE working = 0 AND success = 0 LIMIT ".$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $this->db->exec('UNLOCK TABLES');
        return $data;
    }

    public function getCountPeople()
    {
        $sql = "SELECT count(names.id) as count FROM names LEFT JOIN result ON result.name_id=names.id WHERE result.name_id IS NULL";
        $sth = $this->db->query($sql);

        $data = $sth->fetch();
        return $data['count'];
    }

    public function saveInfoAboutPeople1($arrInf)
    {
        $stmt = $this->db->prepare("INSERT INTO result(title, snippet, url, name_id) VALUES(:title, :snippet, :url, :name_id)");
        $stmt->execute($arrInf);

        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function saveInfoAboutPeople($arrInf)
    {
        $sql =  'INSERT INTO result(title, snippet, url, name_id) VALUES ';

        $insertQuery = [];
        $insertData = [];
        foreach ($arrInf as $row) {
            $insertQuery[] = '(?, ?, ?, ?)';
            $insertData[] = $row['title'];
            $insertData[] = $row['snippet'];
            $insertData[] = $row['url'];
            $insertData[] = $row['name_id'];
        }

        if (!empty($insertQuery)) {
            $sql .= implode(', ', $insertQuery);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($insertData);
        }
    }

    public function saveProxy($proxy)
    {
        $stmt = $this->db->prepare("INSERT INTO proxies(proxy) VALUES(:proxy)");
        $stmt->execute(array('proxy' => $proxy));

        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }


    public function delProxies()
    {
        $this->db->exec("TRUNCATE TABLE proxies");
    }


    public function getProxies()
    {
        $sql = "SELECT proxy FROM proxies";

        $sth = $this->db->prepare($sql);
        $sth->execute();

        $data = $sth->fetchAll();
        return $data;
    }

    public function getAliveProxies()
    {
        $sql = "SELECT proxy FROM proxies WHERE alive = 1";

        $sth = $this->db->prepare($sql);
        $sth->execute();

        $data = $sth->fetchAll();
        return $data;
    }

    public function setAliveProxy($proxy)
    {
        $stmt = $this->db->prepare("UPDATE proxies SET alive = 1  WHERE proxy = :proxy");
        $stmt->execute(array('proxy' => $proxy));

        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function setAliveAllProxies()
    {
        $stmt = $this->db->prepare("UPDATE proxies SET alive = 1");
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function resetAliveProxy($proxy)
    {
        $stmt = $this->db->prepare("UPDATE proxies SET alive = 0  WHERE proxy = :proxy");
        $stmt->execute(array('proxy' => $proxy));

        if ($stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }


    public function resetLivingProxies()
    {
        $stmt = $this->db->prepare("UPDATE proxies SET alive = 0  WHERE alive = 1");
        $stmt->execute();
    }


}
