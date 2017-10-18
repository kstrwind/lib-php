<?php
/****************************************************************
 *                 made by kstrwind                             *
 *                 可以无偿使用，保留该版权声明即可             *
 *                 联系方式： kstrwind@163.com                  *
 ***************************************************************/

const ZABBIX_JSONRPC_VERSION    =   "2.0";
const DEFAULT_ZABBIX_URI = "/api_jsonrpc.php";
const DEFAULT_ZABBIX_AGENT_PORT = 10050;
const DEFAULT_ZABBIX_RETRY = 4;

class ZabbixClient {
    public  $strIp;
    public  $intPort;
    public  $strUri;
    private $strUser;
    private $strPasswd;
    private $strSessionid;
    private $arrBodyTemplate;
    private $strError;
    static  $intId = 1;

    /**
     * construct function
     * @param array
     * format: array(
     *      "ip"    => "127.0.0.1", // api server ip
     *      "port"  =>  80,         // api server port
     *      "uri"   =>  "/api_jsonrpc.php", //api uri
     *      "user"  =>  "Admin",    // an user with administrator privilege
     *      "passwd" => "xxxx",     // passwd 
     *  )
     * @return
     */   
    public function __construct(array $arrConf) {
        if (isset($arrConf["ip"])){
            $this->strIp = $arrConf["ip"];
        }
        if (isset($arrConf["port"])){
            $this->intPort = $arrConf["port"];
        }
        if (isset($arrConf["uri"])){
            $this->strUri = $arrConf["uri"];
        } else {
            $this->strUri = DEFAULT_ZABBIX_URI;
        }
        if (isset($arrConf["user"])){
            $this->strUser = $arrConf["user"];
        }
        if (isset($arrConf["passwd"])){
            $this->strPasswd = $arrConf["passwd"];
        }
        $this->strSessionid = "";
        $this->arrBodyTemplate = array(
            "jsonrpc"   => "2.0",
            "method"    => "",
            "id"        => self::$intId,
        );
    }

    /**
     * get last error message
     * @return string, error message
     */   
    public function error()
    {
        return $this->strError;
    }

    /**
     * set api server conf
     * @param [in] [string] $ip : server ip
     * @param [in] [int] $port: server port
     * @return false, set failed
     * @return true, set succ
     */
    public function setServer($ip, $port)
    {
        $this->resetError();
        if (empty($ip) || empty($port)){
            $this->setError("Invalid server conf ip:[$ip] port[$port]");
            return false;
        }
        $this->strIp    =   $ip;
        $this->intPort  =   $port;
        //should logout
        if ($this->checkLogin()){
            $this->logout();
        }
        return true; 
    }

    /**
     * set api server uri
     * @param [in] [string] $uri : server uri
     * @return false, set failed
     * @return true, set succ
     */
    public function setUri($uri)
    {
        $this->resetError();
        if (empty($uri)){
            $this->setError("Invalid uri conf for uri empty");
            return false;
        }
        $this->strUri   =   $uri;
        return true;
    }

    /**
     * set api server user; if zabbix has login, we'll logout
     * 
     * @param [in] [string] $user : server user name
     * @param [in] [string] $passwd : server password
     * @return false, set failed
     * @return true, set succ
     */
    public function setUser($user, $passwd)
    {
        $this->resetError();
        if (empty($user)){
            $this->setError("Invalid server user conf, user is null");
            return false;
        }

        $this->strUser  =   $user;
        $this->strPasswd=   $passwd;

        //should logout
        if ($this->checkLogin()){
            $this->logout();
        }
        return true;
    }

    /**
     * login zabbix
     * @return : false, login faile
     * @return : true, login succ
     */
    public function login()
    {
        $this->resetError();
        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "user.login";
        $request_body["params"] = array(
            "user"      =>  $this->strUser,
            "password"  =>  $this->strPasswd,
        );
        $request_body["id"] = self::$intId;

        $retry = DEFAULT_ZABBIX_RETRY;
        while ($retry--){
            $res = $this->request("POST", $request_body);
            if (false === $res) {
                //sleep 50ms then retry
                usleep(50000);
                continue;
            }

            $res_info = json_decode($res, true);
            if (empty($res_info["result"])){
                $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
                $err_info .= "login failed, {$res_info["error"]["data"]}";
                $this->setError($err_info);
                continue;
            }
            $this->strSessionid = $res_info["result"];
            return true;
        }
        return false;
    }

    /**
     * logout 
     * @return false, log out failed
     * @return true, log out succ
     */
    public function logout()
    {
        $this->resetError();
        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "user.logout";
        $request_body["params"] = array();
        $request_body["id"] = self::$intId;
        $request_body["auth"] = $this->strSessionid;

        $retry = DEFAULT_ZABBIX_RETRY;
        while ($retry--){
            $res = $this->request("POST", $request_body);
            if (false === $res) {
                //sleep 50ms then retry
                usleep(50000);
                continue;
            }

            $res_info = json_decode($res, true);
            if (empty($res_info["result"])){
                $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
                $err_info .= "logout failed, {$res_info["error"]["data"]}";
                $this->setError($err_info);
                continue;
            }
            $this->strSessionid = "";
            return true;
        }
        return false;
    }

    /**
     * get specified hostgroup info, with host list  and template list
     * @param [in] [string] $hostgroup : hostgroup name 
     * @return : false
     * @return : array, hostgroup info; if empty array, not exists
     */
    public function hostgroup_info($hostgroup)
    {
        $this->resetError();
        if (!$this->checkLogin()){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroup info failed for user not login";
            $this->setError($err_info);
            return false;
        }

        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "hostgroup.get";
        $request_body["params"] = array(
            "selectHosts"       =>  array(
                "hostid",
                "host",
            ),
            "selectTemplates"   =>  array(
                "templateid",
                "host",
            ),
            "filter"    => array(
                "name"  => array($hostgroup),
            ),
        );
        $request_body["id"]     =   self::$intId;
        $request_body["auth"]   =   $this->strSessionid;

        $res = $this->request("POST", $request_body);
        if (false === $res) {
            return false;
        }

        $res_info = json_decode($res, true);

        //failed
        if (!isset($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroup info failed, {$res_info["error"]["data"]}";
            $this->setError($err_info);
            return false;
        }

        //if not exists
        if (empty($res_info["result"])){
            return array();
        }

        //has data
        return $res_info["result"][0];
    }

    /**
     * get all hostgroups info, with host list  and template list
     * @return : false
     * @return : array, hostgroup info
     */
    public function hostgroup_all()
    {
        $this->resetError();
        if (!$this->checkLogin()){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroups info failed for user not login";
            $this->setError($err_info);
            return false;
        }

        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "hostgroup.get";
        $request_body["params"] = array(
            "selectHosts"       =>  array(
                "hostid",
                "host",
            ),
            "selectTemplates"   =>  array(
                "templateid",
                "host",
            ),
        );
        $request_body["id"]     =   self::$intId;
        $request_body["auth"]   =   $this->strSessionid;

        $res = $this->request("POST", $request_body);
        if (false === $res) {
            return false;
        }

        $res_info = json_decode($res, true);

        //failed
        if (!isset($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroups info failed, {$res_info["error"]["data"]}";
            $this->setError($err_info);
            return false;
        }

        //has data
        return $res_info["result"];
    }

    /**
     * add hostgroup
     * @param [in] [string] $hostgroup : hostgroup name 
     * @return : false, add failed
     * @return : int ,group id, add succ
     */
    public function hostgroup_add($hostgroup)
    {
        $this->resetError();
        if (!$this->checkLogin()){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroups info failed for user not login";
            $this->setError($err_info);
            return false;
        }

        //check if hostgroup exists
        $hostgroup_info = $this->hostgroup_info($hostgroup);

        //host group has exists
        if (!empty($hostgroup_info["groupid"])){
            return $hostgroup_info["groupid"];
        }

        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "hostgroup.create";
        $request_body["params"] = array(
            "name" => $hostgroup,
        );
        $request_body["id"] = self::$intId;
        $request_body["auth"] = $this->strSessionid;

        $res = $this->request("POST", $request_body);
        if (false === $res) {
            return false;
        }

        $res_info = json_decode($res, true);

        //failed, for group id not return; this situation not exists
        if (isset($res_info["result"]) && empty($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroup add failed for no groupid return";
            $this->setError($err_info);
            return false;
        }

        //succ
        if (!empty($res_info["result"]["groupids"])){
            return $res_info["result"]["groupids"][0];
        }

        if (isset($res_info["error"]) && $res_info["error"]["code"] != -32602){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroup add failed, {$res_info["error"]["data"]}";
            $this->setError($err_info);
            return false;
        }

        //has exists
        if (isset($res_info["error"]) && $res_info["error"]["code"] == -32602){
            $retry = 3;
            while ($retry--){
                $hostgroup_info = $this->hostgroup_info($hostgroup);
                if (false === $hostgroup_info) {
                    continue;
                }
                if (empty($hostgroup_info)){
                    continue;
                }
                return $hostgroup_info["groupid"];
            }
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hostgroup add succ, but groupid not found, please add retry";
            $this->setError($err_info);
            return false;
        }
        return true; 
    }

    /**
     * host info
     * @param [in] [string] $host : host name 
     * @return : false, host info failed
     * @return : array(), host info
     * @return : empty array(), host not exists
     */
    public function host_info($host)
    {
        $this->resetError();
        if (!$this->checkLogin()){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "host info failed for user not login";
            $this->setError($err_info);
            return false;
        }

        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "host.get";
        $request_body["params"] = array(
            "output" => array("hostid", "name", "status"),
            "selectGroups" => "extend",
            "filter" => array(
                "host" => array($host),
            ),
        );
        $request_body["id"] = self::$intId;
        $request_body["auth"] = $this->strSessionid;

        $res = $this->request("POST", $request_body);
        if (false === $res) {
            return false;
        }

        $res_info = json_decode($res, true);

        //failed
        if (!isset($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "host info failed, {$res_info["error"]["data"]}";
            $this->setError($err_info);
            return false;
        }

        //host not exists
        if (empty($res_info["result"])){
            return array();
        }

        //host exists
        return $res_info["result"][0];
    }

    /**
     * host all info
     * @return : false, host info failed
     * @return : array(), hosts info
     * @return : empty array(), hosts not exists
     */
    public function host_all()
    {
        $this->resetError();
        if (!$this->checkLogin()){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hosts info failed for user not login";
            $this->setError($err_info);
            return false;
        }

        $request_body = $this->arrBodyTemplate;
        $request_body["method"] = "host.get";
        $request_body["params"] = array(
            "output" => array("hostid", "name", "status"),
            "selectGroups" => "extend",
        );
        $request_body["id"] = self::$intId;
        $request_body["auth"] = $this->strSessionid;

        $res = $this->request("POST", $request_body);
        if (false === $res) {
            return false;
        }

        $res_info = json_decode($res, true);

        //failed
        if (!isset($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hosts info failed, {$res_info["error"]["data"]}";
            $this->setError($err_info);
            return false;
        }

        //host not exists or exists
        return $res_info["result"];
    }

    /**
     * add host
     * @param [in] [string] $host : host name
     * @param [in] [string] $ip : host ip
     * @param [in] [int] $port : host agent port
     * @param [in] [int] $groupid : host group id 
     * @param [in] [array] $extend : host other info, current not use
     * @return false, add failed
     * @return int hostid, add succ
     */   
    public function host_add($host, $ip, $port, $groupid, $extend= array())
    {
        $this->resetError();
        if (!$this->checkLogin()){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hosts info failed for user not login";
            $this->setError($err_info);
            return false;
        }

        // status: 1, host not create; 2. host create ,but not in groupid; 3. host create and in the groupid
        $host_exists = 1;
        $group_ids = array();
        $host_id = "";
        $host_info = $this->host_info($host);

        //check failed, we think not exists
        if (false === $host_info){
            $host_exists = 1;
            //host not exists
        } elseif (empty($host_info)){
            $host_exists = 1;
            //host exists
        } else {
            $host_id = $host_info["hostid"]; 
            foreach ($host_info["groups"] as $groupinfo){
                $group_ids[] = array("groupid" => $groupinfo["groupid"]);
                //host exists and in the host group
                if ($groupinfo["groupid"] == $groupid){
                    $host_exists = 3; 
                    return $host_id;
                }
            }

            //host exists, but not in the group
            $host_exists = 2;
        }
        $group_ids[] = array("groupid" => $groupid);
        $request_body = $this->arrBodyTemplate;
        $request_body["id"] = self::$intId;
        $request_body["auth"] = $this->strSessionid;
        if ($host_exists == 1) {
            $request_body["method"] = "host.create";
            $request_body["params"] = array(
                "host"  =>  $host,
                "interfaces"    => array(array(
                    "type"  =>  1,
                    "ip"    =>  $ip,
                    "main"  =>  1,
                    "port"  =>  $port, 
                    "useip" =>  1,
                    "dns"   =>  "",
                )),
                "groups"    =>  $group_ids,
            );
        } else {
            $request_body["method"] = "host.update";
            $request_body["params"] = array(
                "hostid"    =>  $host_id,
                "groups"    =>  $group_ids,
            );
        }
          
        $res = $this->request("POST", $request_body);
        if (false === $res) {
            return false;
        }

        $res_info = json_decode($res, true);

        //failed, for group id not return; this situation not exists
        if (isset($res_info["result"]) && empty($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hosts add failed for hostid not return";
            $this->setError($err_info);
            return false;
        }

        //succ
        if (!empty($res_info["result"]["hostids"])){
            return $res_info["result"]["hostids"][0];
        }

        //error
        if (!isset($res_info["result"])){
            $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
            $err_info .= "hosts add failed, {$res_info["error"]["data"]}";
            $this->setError($err_info);
            return false;
        }

        //else 
        $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
        $err_info .= "hosts add failed for unknown error";
        $this->setError($err_info);
        return false;
    }

    /**
     * reuqest zabbix api server
     * @param [in] [string] $method: http method , like GET/POST 
     * @param [in] [array] $body: http body, array
     * @return false if exec failed; json string if success
     */    
    private function request($method, $body)
    {
        self::$intId++;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://" . $this->strIp . ":" . $this->intPort . $this->strUri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json-rpc"));

        $r_method= strtoupper($method);

        //current not use GET
        if ($r_method != "GET") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $z_res = curl_exec($ch);

        do {
            if ($z_res === false) {
                $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
                $err_info .= "curl failed for " . curl_error($ch);
                $this->setError($err_info);
                break;
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code != 200) {
                $err_info = __CLASS__ . ":" . __FUNCTION__ . ":" . __LINE__ . ": ";
                $err_info .= "curl failed, http_code[$http_code] " . curl_error($ch);
                $this->setError($err_info);
                $z_res = false;
                break;
            }
        } while (false);

        curl_close($ch);
        return $z_res;
    }

    /**
     * check if user has login
     * @return false, not login
     * @return true, has login
     */
    private function checkLogin()
    {
        if (empty($this->strSessionid)){
            return false;
        }
        return true;
    }

    /**
     * set error messgae
     * @param [in] [string] $errMsg : error message
     */
    private function setError($errMsg)
    {
        $this->strError = $errMsg;
    }

    /**
     * reset error message to null
     */ 
    private function resetError()
    {
        $this->strError = "";
    }
}
