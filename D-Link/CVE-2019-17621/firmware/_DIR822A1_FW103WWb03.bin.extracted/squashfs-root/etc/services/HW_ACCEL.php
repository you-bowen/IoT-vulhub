<?
include "/htdocs/phplib/trace.php";
include "/htdocs/phplib/phyinf.php";
include "/htdocs/phplib/xnode.php";

fwrite("w",$START, "#!/bin/sh\n");
fwrite("w",$STOP,  "#!/bin/sh\n");

function startcmd($cmd)	{fwrite(a,$_GLOBALS["START"], $cmd."\n");}
function stopcmd($cmd)	{fwrite(a,$_GLOBALS["STOP"], $cmd."\n");}
function error($errno)	{startcmd("exit ".$errno); stopcmd( "exit ".$errno);}

function HWNATsetup($dis_fastnat,$wan_type)
{
	$file_name = "/tmp/hw_accel_mode";
	$cur_accel_mode = fread("",$file_name);
	if($dis_fastnat == 1)
	{
		startcmd('echo Disable Chip Vendor Fast NAT  ...	> /dev/console');
		startcmd('echo 0 > /proc/fast_nat');
	}
	else
	{
		/*
			We couldn't arbitrarily flush fast_nat, otherwise it will cause
			ping fail. It should be realtek's driver bug.
		*/
		if($wan_type != "pptp" && $wan_type != "l2tp")
		{
			startcmd('echo Enable Chip Vendor Fast NAT  ...	> /dev/console');
			startcmd('echo 2 > /proc/fast_nat; sleep 1');
			startcmd('echo 1 > /proc/fast_nat');
		}
	}
	
	if($wan_type == "pptp") 
	{
		startcmd('echo 0 > /proc/fast_pppoe');
		$l2tp_enable = fread("e","/proc/fast_l2tp");
		if($l2tp_enable == 1) {startcmd('echo 0 > /proc/fast_l2tp');}
		
		if($cur_accel_mode != "pptp")
		{
			/*
				We just can do once for fast_nat, otherwise it will cause
				ping fail. It should be realtek's driver bug.
			*/
			if($dis_fastnat == 0) 
			{
				startcmd('echo Enable Chip Vendor Fast NAT  ...	> /dev/console');
				startcmd('echo 1 > /proc/fast_nat');
			}
			fwrite("w+",$file_name,"pptp");
		}
		$pptp_enable = fread("e","/proc/fast_pptp");
		if($pptp_enable == 0) {startcmd('echo 1 > /proc/fast_pptp');}
		stopcmd('echo 0 > /proc/fast_pptp');
	}
	else if($wan_type == "l2tp")
	{
		startcmd('echo 0 > /proc/fast_pppoe');
		$pptp_enable = fread("e","/proc/fast_pptp");
		if($pptp_enable == 1) {startcmd('echo 0 > /proc/fast_pptp');}
		
		if($cur_accel_mode != "l2tp")
		{
			/*
				We just can do once for fast_nat, otherwise it will cause
				ping fail. It should be realtek's driver bug.
			*/
			if($dis_fastnat == 0) 
			{
				startcmd('echo Enable Chip Vendor Fast NAT  ...	> /dev/console');
				startcmd('echo 1 > /proc/fast_nat');
			}
			fwrite("w+",$file_name,"l2tp");
		}
		$l2tp_enable = fread("e","/proc/fast_l2tp");
		if($l2tp_enable == 0) {startcmd('echo 1 > /proc/fast_l2tp');}
		stopcmd('echo 0 > /proc/fast_l2tp');
	}
	else if($wan_type == "pppoe")
	{
		$pptp_enable = fread("e","/proc/fast_pptp");
		if($pptp_enable == 1) {startcmd('echo 0 > /proc/fast_pptp');}
		$l2tp_enable = fread("e","/proc/fast_l2tp");
		if($l2tp_enable == 1) {startcmd('echo 0 > /proc/fast_l2tp');}
		startcmd('echo 1 > /proc/fast_pppoe');
		fwrite("w+",$file_name,"pppoe");
	}
	else
	{
		startcmd('echo 0 > /proc/fast_pppoe');
		$pptp_enable = fread("e","/proc/fast_pptp");
		if($pptp_enable == 1) {startcmd('echo 0 > /proc/fast_pptp');}
		$l2tp_enable = fread("e","/proc/fast_l2tp");
		if($l2tp_enable == 1) {startcmd('echo 0 > /proc/fast_l2tp');}
		if(isfile($file_name) == 1) {unlink($file_name);}
	}
	
	startcmd('echo Enable Alpha Software NAT as Default ...	> /dev/console');
	startcmd('echo 1 > /proc/sys/net/ipv4/netfilter/ip_conntrack_fastnat');
	stopcmd('echo 0 > /proc/sys/net/ipv4/netfilter/ip_conntrack_fastnat');
}

$layout = query("/runtime/device/layout");
if ($layout=="router")
{
	$dis_fastnat_flag = 0;
	$wan1_active = 0;
	$wan2_active = 0;
	$wan_mode = "";

	$if1path = XNODE_getpathbytarget("", "inf", "uid", "WAN-1", 0);
	$if2path = XNODE_getpathbytarget("", "inf", "uid", "WAN-2", 0);
	if ($if1path != "") {$wan1_active = query($if1path."/active");}
	if ($if2path != "") {$wan2_active = query($if2path."/active");}
	if($wan1_active == "1")
	{
		$if1_inet	= query($if1path."/inet");
		$if1_inetp	= XNODE_getpathbytarget("/inet", "entry", "uid", $if1_inet, 0);
		$if1_addrtype = query($if1_inetp."/addrtype");
		$if1_over   = query($if1_inetp."/ppp4/over");
		
		if($if1_addrtype != "ipv4")
		{
			/* check PPPeE */
			if($if1_over == "eth") {$wan_mode = "pppoe";}
			/* check PPTP */
			if($if1_over == "pptp"){$wan_mode = "pptp";}
			/* check L2TP */
			if($if1_over == "l2tp"){$wan_mode = "l2tp";}
		}
	}
	
	if($wan2_active == "1")
	{
		$if2_inet	= query($if2path."/inet");
		$if2_inetp	= XNODE_getpathbytarget("/inet", "entry", "uid", $if2_inet, 0);
		$if2_addrtype = query($if2_inetp."/addrtype");
		$if2_over   = query($if2_inetp."/ppp4/over");
		
		if($if2_addrtype != "ipv4")
		{
			/* check PPPeE */
			if($if2_over == "eth") {if($wan1_active != "1") {$wan_mode = "pppoe";}}
			/* check PPTP */
			if($if2_over == "pptp"){if($wan1_active != "1") {$wan_mode = "pptp";}}
			/* check L2TP */
			if($if2_over == "l2tp"){if($wan1_active != "1") {$wan_mode = "l2tp";}}
		}
	}

	/* +++ START Hardware NAT and Fast NAT +++ */
	if($wan1_active == "1" || $wan2_active == "1") {HWNATsetup($dis_fastnat_flag,$wan_mode);}
}

error(0);
?>
