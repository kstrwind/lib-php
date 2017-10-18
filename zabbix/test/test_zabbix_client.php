<?php
require "zabbix_client.php";

$conf = array(
    "ip" => "192.168.56.101",
    "port" => 8003,
    "user"=> "Admin",
    "passwd" => "zabbix",
);

$z_case = new ZabbixClient($conf);

//test case
// 1. set server, set user, set uri 
function test_set()
{
    global $z_case;

    //uri check
    //pass
    var_dump($z_case);
    var_dump($z_case->setUri(""));
    var_dump($z_case);
    var_dump($z_case->setUri("xxxxxxxx"));
    var_dump($z_case);
    var_dump($z_case->setUri("/api_jsonrpc.php"));
    var_dump($z_case);
    echo "-----------------------------------------\n";

    //server check
    var_dump($z_case->setServer("", 1234));
    var_dump($z_case);
    var_dump($z_case->setServer("192.168.56.101", 8003));
    var_dump($z_case);
    echo "-----------------------------------------\n";

    var_dump($z_case->setUser("Admin", "zabbix"));
    var_dump($z_case);
    var_dump($z_case->login());
    var_dump($z_case);
    //has reset session
    var_dump($z_case->setUser("Admin", "zabbix"));
    var_dump($z_case);
}
//test hostgroup
function test_hostgroup()
{
    global $z_case;
    $z_case->login();

    //test hostgroup one with not exists --pass
    /*
    var_dump($z_case);
    var_dump($z_case->hostgroup_info("HelloGroup"));
    var_dump($z_case);
    */
    
    //test hostgroup one exists --pass
    /*
    var_dump($z_case->hostgroup_info("Zabbix servers"));
    var_dump($z_case);
    */
    //test hostgroup all --pass
    /*
    var_dump($z_case->hostgroup_all());
    var_dump($z_case);
    */ 

    //test hostgroupadd not exists --pass
    /*var_dump($z_case->hostgroup_add("test_group_2"));
    var_dump($z_case);
     */
    //test hostgroupadd has exists
    //pass
    var_dump($z_case->hostgroup_add("test_group_2"));
    var_dump($z_case);
}

function test_host()
{
    global $z_case;
    $z_case->login();
    /*
    //host one not exists --pass
    var_dump($z_case->host_info("slee4"));
    var_dump($z_case);

    //host one exists  --pass
    var_dump($z_case->host_info("slee2"));
    var_dump($z_case);
     */
    //host all --pass
    //var_dump($z_case->host_all());
    //var_dump($z_case);

    // host add one not exists -pass
    //var_dump($z_case->host_add("slee3", "192.168.56.101", 10050, 37));
    //var_dump($z_case);

    //host add one exists not in group
    var_dump($z_case->host_add("slee3", "192.168.56.101", 10050, 17));
    var_dump($z_case);

    //host add one exists and in group --pass
    //var_dump($z_case->host_add("slee3", "192.168.56.101", 10050, 37));
    //var_dump($z_case);
}

/*
echo "hostgroup info\n";
var_dump($z_case->hostgroup_info("ad-test"));

echo "hostgroup info\n";
var_dump($z_case->hostgroup_info("ad-test2"));

echo "hostgroup info all\n";
var_dump($z_case->hostgroup_all());
 */

function main()
{
    //test_set();
    //test_hostgroup();
    test_host();
}

main();
