#!/bin/sh
<?
// This script is used for performing DAD when WANPORT LINKUP.
include "/htdocs/webinc/config.php";
include "/htdocs/phplib/xnode.php";
include "/htdocs/phplib/trace.php";


//Get the WAN PORT devname.
$wan_ll_p = XNODE_getpathbytarget("", "inf", "uid", $WAN1, "0");
$phyinf = get("", $wan_ll_p."/phyinf");
$phyinf_p = XNODE_getpathbytarget("/runtime", "phyinf", "uid", $phyinf, "0");

$ll_devname = get ("", $phyinf_p."/name");

$ll_addr = get("", $phyinf_p."/ipv6/link/ipaddr");

if ($ll_addr != "" && $ll_devname != "")
{
	// Huanyao: Reset IPv6 linklocal address to send DAD when WAN port linkup.
	echo "ip -6 addr del ".$ll_addr."/64 dev ".$ll_devname." \n";
	echo "ip -6 addr add ".$ll_addr."/64 dev ".$ll_devname." \n";

	// HuanYao: WORKAROUND. sometimes ipv6 will be disabled after remove link-local address. (2016.1.26)
	echo "echo 0 > /proc/sys/net/ipv6/conf/".$ll_devname."/disable_ipv6 \n";
	
	//HuanYao: When performing IPv6 DAD, we should prevent any IPv6 address assignment. 2015.07.15
	$lock_filename = "/var/".$ll_devname.".ipv6dad_lock";
	echo "echo 1 > ".$lock_filename."\n";
	echo "xmldbc -t 'IPV6DAD:5:rm ".$lock_filename."'\n";

}
echo "exit 0 \n";

?>
