<?php 
require_once(dirname(__FILE__).'/functions.php');
date_default_timezone_set('Africa/Nairobi');
	if (isset($_POST['txtUsername']))
	{
		$_SESSION['SWIFT_Login_ID'] = $_POST["txtUsername"];
		$loginname= rawurlencode($_POST["txtUsername"]); 
		$Password = swift_encrypt($_POST["txtPassword"]); 
		$trials = $_POST["txtTrials"]; 		
		
		// prepare a query with a question mark placeholder
$sql = "SELECT * FROM tbl_Users WHERE User_ID = ? AND Password = ?";

// specify the value for the placeholder in an array
$params = array($loginname, $Password);

// execute the query with sqlsrv_query
$stmt = sqlsrv_query( $sqlconnect, $sql, $params);
$numRows = sqlsrv_num_rows($stmt);
if( $stmt === false ) {
die( print_r( sqlsrv_errors(), true));
}

// fetch and display the results
while( $row = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
//echo $row['User_ID'].", ".$row['Full_Name'].", ".$row['Status']."<br />";

//echo($row['Password']);
//die("Nairobi $row['Full_Name']");


		$_SESSION['SWIFT_Dual_Auth'] = sqlrequest("SELECT * FROM tbl_Setup_List WHERE Item_Name = ?","List_Item","DUAL_AUTH");
		$_SESSION['SWIFT_P_Expires'] = sqlrequest("SELECT * FROM tbl_Setup_List WHERE Item_Name = ?","List_Item","P_EXPIRY");
		$_SESSION['SWIFT_P_Length'] = sqlrequest("SELECT * FROM tbl_Setup_List WHERE Item_Name = ?","List_Item","P_LENGTH");


//echo($row[$_SESSION['SWIFT_Dual_Auth']]); exit;


if ($row['Status']!="1") 
			{
				//echo($loginname);
				$Login ="disabled";
				proc_Save_Log ('Attempt to login with disabled account',$loginname);
			}	
	else if ($row['Mod_Status']=="1")  // && $_SESSION['SWIFT_Dual_Auth'] == "1") 
			{
				$Login ="temp disabled";
				echo($row['Password']);
				proc_Save_Log ('Attempt to login with un-approved account',$loginname);
			}
			else
			{
				if (proc_passwordExpired($row['PCreatedOn'],$row['Change_On_Login']) == true and $row['Change_On_Login']=="0") 
				{
					$Login ="password expired";
					proc_Save_Log ('Attempt to login with expired password',$loginname);
				}
				else
				{
					
					$_SESSION['SWIFT_Current_User_Ref'] = $row['RefNo'];
					$_SESSION['SWIFT_Current_User'] = $row['User_ID'];
					$_SESSION['SWIFT_Full_Name'] = $row['Full_Name'];			
					$_SESSION['SWIFT_User_Level'] = $row['User_Level'];				
					$_SESSION['SWIFT_Branch_Code']= $row['Branch_Code'];

					
					$_SESSION['SWIFT_Branch_Name'] = sqlrequest("SELECT * FROM tbl_Branches WHERE Branch_Code = ?","Branch_Name", $row['Branch_Code']);

					//$_SESSION['SWIFT_Branch_Name'] = sqlrequest("SELECT * FROM tbl_Branches WHERE Branch_Code = '".$row['Branch_Code'] . "'","Branch_Name");
		
					if ($_SESSION['SWIFT_User_Level'] =="DATA ENTRY") {$_SESSION['SWIFT_Message_Status']="0"; $_SESSION['SWIFT_Next_Message_Status']="1";}
					if ($_SESSION['SWIFT_User_Level'] =="VERIFIER") {$_SESSION['SWIFT_Message_Status']="1"; $_SESSION['SWIFT_Next_Message_Status']="2";}			
					if ($_SESSION['SWIFT_User_Level'] =="AUTHORIZER") {$_SESSION['SWIFT_Message_Status']="2"; $_SESSION['SWIFT_Next_Message_Status']="3";}						
					
					proc_Save_Log ('Logged In Successfully', $loginname);
					
					$Login = true;

					//echo($Login]); exit;

					//echo '<script>alert('$Login')</script>';
					
					if ($row['Change_On_Login']=="1") 
					{	
						echo "<script type='text/javascript'>window.location.replace('change password.php?pg=login'); window.document.getElementById('txtPassword').focus();</script>";
					}
					else
					{
						echo "<script type='text/javascript'>window.close(); window.opener.location.replace('index.php?pg=home'); </script>";
					}
				}
			}

//$numRows = sqlsrv_num_rows($stmt);
}


// free the statement resource
sqlsrv_free_stmt( $stmt);


	}
	
	sqlsrv_close($sqlconnect);
	
	function proc_passwordExpired($createdOn,$change)
	{
		$timeDiff=array();
		if($createdOn!="")
		{
			$timeDiff=proc_time_difference($createdOn);
			
			$_SESSION['SWIFT_PWD_Expiry'] = 30 - $timeDiff['days'];
			$_SESSION['SWIFT_PWD_Age'] = $timeDiff['days'];
			
			if($timeDiff['days']>30 && $_SESSION['SWIFT_P_Expires'] == "1") 
			{
				if ($change=="0") {$action="Please contact the administrator";}
				else if ($change=="1") {$action="You will be requried to change your password";}
				
				//echo '<script type="text/javascript">alert("Your password is '.$timeDiff['days'].' days old and has expired. '.$action.'.");</script>'; return true;

				echo "<script type='text/javascript'>window.location.replace('change password.php?pg=login'); window.document.getElementById('txtPassword').focus();</script>";
			}

			else {return false;}
		}
	}
	
	function proc_time_difference($PasswordCreatedOn)
	{
		$timeDifference['start']  = $PasswordCreatedOn->format('U'); //strtotime( $PasswordCreatedOn );
		$timeDifference['now']  = date(U);
		if( $timeDifference['start']!==-1 && $timeDifference['now']!==-1 )
		{
			if($timeDifference['now'] >= $timeDifference['start'] )
			{
				$diff = $timeDifference['now'] - $timeDifference['start'];
				if($days=intval((floor($diff/86400))))
				$diff = $diff % 86400;
				if( $hours=intval((floor($diff/3600))) )
				$diff = $diff % 3600;
				if( $minutes=intval((floor($diff/60))) )
				$diff = $diff % 60;
				$diff  = intval( $diff );            
				return(array('days'=>$days, 'hours'=>$hours, 'minutes'=>$minutes, 'seconds'=>$diff));
			}
    	}
	
		return(array('days'=>0, 'hours'=>0, 'minutes'=>0, 'seconds'=>0));
   }
?>
	
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<title>SWIFTLINK</title>
	<link rel="stylesheet" href="files/main2.css" type="text/css" charset="utf-8" />
	
    <style type="text/css">
<!--
.style1 {color: #FFFFFF}
.style7 {color: #5C5C5C}
.style8 {font-family: Geneva, Arial, Helvetica, sans-serif}
.style10 {
	font-family: Geneva, Arial, Helvetica, sans-serif;
	font-size: 12px;
	color: #FF0000;
}
-->
    </style>
</head>

<body OnLoad="document.loginform.txtUsername.focus();">
<div id="body_login" style="background-color:#154779; margin-top: 5px;" align="center">
  <form action="<?php echo htmlentities($_SERVER['PHP_SELF']);?>" method="POST" autocomplete="off" name="loginform" id="loginform">
  <table width="532" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td width="283">&nbsp;</td>
      <td width="100">&nbsp;</td>
      <td width="124">&nbsp;</td>
      <td width="25">&nbsp;</td>
      </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>	
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td rowspan="7" align="center" valign="middle"><img src="files/kenex.jpg" alt="" width="157" height="143" /></td>
      <td colspan="3" bgcolor="#EEEEEE"><div align="center">
        <?php 				
			if ($Login =="false")
			{	
				Echo ("Successfully connected with myDB database" + $trials); 
				if ($trials < 3)
				{
					$trials++;
					print '<img src="files/restricted.png" width="24" height="24" /><span class = "style10"> INVALID LOGIN. Please Try Again! Trial '.$trials.'</span>';	
					
					if ($trials == 3)
					{
					
						$sqlquery1="UPDATE tbl_Users SET Status = '0' WHERE User_ID = '$loginname';";
						//echo($sqlquery); exit;
						
						$results1= sqlsrv_query($GLOBALS['sqlconnect'],$sqlquery1,[], ["Scrollable" => SQLSRV_CURSOR_KEYSET ]);
						
						print '<img src="files/restricted.png" width="24" height="24" /><span class = "style10"> Account Locked. Contact the Administrator!</span>';
					}
				}
				
																		
			}
			else if ($Login =="disabled")
			{		
				print '<img src="files/restricted.png" width="24" height="24" /><span class = "style10"> ACCOUNT IS DISABLED!</span>';															
			}
			else if ($Login =="temp disabled")
			{		
				print '<img src="files/restricted.png" width="24" height="24" /><span class = "style10"> ACCOUNT PENDING AUTHORIZATION!</span>';															
			}
			else if ($Login =="password expired")
			{		
				print '<img src="files/restricted.png" width="24" height="24" /><span class = "style10"> PASSWORD EXPIRED!</span>';															
			}
		?>
      </div></td>
      </tr>
    <tr>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      </tr>
    <tr>
      <td height="25" bgcolor="#EEEEEE"><div align="right"><span class="style1"><span class="style7"><span class="style8">Username</span>&nbsp;:&nbsp;</span></span></div></td>
      <td bgcolor="#EEEEEE"><div align="right">
        <input name="txtUsername" type="text" id="txtUsername" style="width:130px"/>
      </div></td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      </tr>
    <tr>
      <td height="25" bgcolor="#EEEEEE"><div align="right"><span class="style1"><span class="style7"><span class="style8">Password</span>&nbsp;:&nbsp;</span></span></div></td>
      <td bgcolor="#EEEEEE"><div align="right">
        <input name="txtPassword" type="password" id="txtPassword" style="width:130px"/>
      </div></td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      </tr>
    <tr>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      </tr>
    <tr>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      <td bgcolor="#EEEEEE"><div align="right">
        <input type="submit" name="Submit" value="Login" />
      </div></td>
      <td bgcolor="#EEEEEE"></td>
      </tr>
    <tr>
      <td colspan="2" bgcolor="#EEEEEE"><input name="txtTrials" type="text" id="txtTrials" style="display:none" value="<?php echo htmlentities($trials); ?>"/></td>
      <td bgcolor="#EEEEEE">&nbsp;</td>
      </tr>
    <tr>
      <td><div align="center"><span class="style1"> Kenex © <?php
    $copyYear = 2010; // Set your website start date
    $curYear = date('Y'); // Keeps the second year updated
      echo $copyYear . (($copyYear != $curYear) ? '-' . $curYear : '');
  ?>.</span></div></td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td><div align="center" class="style1">www.kenexnbi.com</div></td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
      </tr>
  </table>
  </form>
</div>
</body>
</html>
