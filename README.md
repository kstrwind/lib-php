[TOC]
# 介绍
这是个人在开发过程中逐步总结的用于提升开发效率的PHP lib库， 大家可以无偿使用，但是需要保留文件首部的版权声明

# 库说明
## zabbix
### zabbix_client

*功能说明*

这是zabbix的客户端的一个简单的SDK，可以用来从zabbix服务器查询和添加主机组、主机

*API说明*

- ZabbixClient->hostgroup_info(), 查询指定的主机组的情况， 返回值为false，表示失败；返回空数组，表示主机组不存在；返回有信息，则表示执行成功
- ZabbixClient->hostgroup_all(), 查询zabbix数据库里所有的主机组信息，返回值同上
- ZabbixClient->hostgroup_add(), 创建指定名称的主机组，返回false表示创建失败；创建成功时，返回一个主机组ID的 int值。该接口是可重入的——如果主机组已经存在，则会返回该主机组的ID
- ZabbixClient->host_info(), 查询指定主机的信息，返回false表示执行失败；返回空数组表示主机不存在；返回数组，包含有书主机信息，则表示成功
- ZabbixClient->host_all(), 查询Zabbix数据库里所有的主机信息，返回值同上
- ZabbixClient->host_add(), 创建指定HOST和主机组的主机信息，返回false表示创建失败；返回hostid表示创建成功。 该接口也是可重入的——如果主机不存在，则创建；如果主机存在，但是不在指定的主机组里，则更新主机的信息，添加新的主机组；如果主机存在指定的主机组里，则返回hostid

*使用说明*
```
1. 创建一个ZabbixClient实例：
$conf = array(
    "ip" => "192.168.56.101",
    "port" => 8003,
    "user"=> "Admin",
    "passwd" => "zabbix",
);

$z_case = new ZabbixClient($conf);

2. ZabbixClient登录到Zabbix服务器，获取到认证token
$res = $z_case->login();

3. 登录成功，可以执行各种操作
$res  = $z_case->host_all();

4. 如果返回值为false,可以调用error()成员函数获取到错误信息
$err_msg = $z_case->error();

5. 操作完成后，需要登出zabbix,防止sessionid被服务器持久保留
$res = $z_case->logout();
```
*二次开发说明*
```
1. 新增一个API
    新增API时， 需要注意几点：
    A. 在API开始时，需要resetError(), 防止上一次执行失败的信息持续保留，导致报错信息不匹配
    B. 在逻辑执行前， 需要先调用 checkLogin() 检查下登录情况,若未登录，则重新登录或直接返回失败；尽量在端上提前检查出问题，提高系统的性能
    C. 请根据Zabbix官方的API描述去填写请求的参数
    D. 调用request()成员函数来发送请求
    E. 出错时，出错信息里尽量包含类名+函数名+行号，这样在集成到大型系统后能很方便地通过日志来发现问题点。 如果是requst()直接返回false,这时不需要重新设置error信息，因为request里已经设置了出错信息，外部再设置的话回覆盖curl出错信息。

2. 扩展原有API
    当已有的API返回的字段不符合需求时，可以根据官方的API文档描述来修改请求参数
```