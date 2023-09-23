<?php

$plugin = "zfs.master";
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

require_once $docroot."/webGui/include/Helpers.php";
require_once $docroot."/plugins/".$plugin."/include/ZFSMBase.php";
require_once $docroot."/plugins/".$plugin."/include/ZFSMError.php";
require_once $docroot."/plugins/".$plugin."/include/ZFSMHelpers.php";
require_once $docroot."/plugins/".$plugin."/backend/ZFSMOperations.php";

$zfsm_cfg = loadConfig(parse_plugin_cfg($plugin, true));

$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

function resolveAnswerCodes($answer) {
	foreach($answer['succeeded'] as $key => $value):
		$answer['succeeded'][$key] = resolve_error($value);
	endforeach;

	foreach($answer['failed'] as $key => $value):
		$answer['failed'][$key] = resolve_error($value);
	endforeach;

	return $answer;
}

function returnAnswer($ret, $title, $success_text, $failed_text, $refresh) {
	if ($refresh):
		refreshData();
	endif;

	$answer = resolveAnswerCodes($ret);

	if (count($answer['succeeded']) > 0):
		zfsnotify( $title, $success_text." for:<br>".implodeWithKeys("<br>", $answer['succeeded']), $err,"normal");
	endif;

	if (count($answer['failed']) > 0):
		zfsnotify( $title, $failed_text." for:<br>".implodeWithKeys("<br>", $answer['failed']), $err,"warning");
	endif;

	echo json_encode($answer);

	return;
}

switch ($_POST['cmd']) {
	case 'refreshdata':
		refreshData();
		break;
	case 'createdataset':
		$zdataset = $_POST['data']['zpool']."/".$_POST['data']['name'];
		$zfs_cparams = cleanZFSCreateDatasetParams($_POST['data']);

		$ret = createDataset( $zdataset, $zfs_cparams);

		returnAnswer($ret, "ZFS Dataset Creation", "Dataset created successfully", "Unable to create dataset", true);

/*		$passphrase = $zfs_cparams['passphrase'] ?? "";
		unset($zfs_cparams['passphrase']);

		$cmd_line = createZFSCreateDatasetCMDLine($zfs_cparams).$boutput_str;

		if ($zfs_cparams['encryption'] == 'on'):
			$cmd_line = "echo ".escapeshellarg($passphrase)." | echo ".escapeshellarg($passphrase)." | ".$cmd_line;
		endif;

		$ret = execCommand($cmd_line, $exec_result);
		
		if ($ret == 0):
			zfsnotify( "ZFS Create", "Creation of dataset ".$zfs_cparams['zpool']."/".$zfs_cparams['name']." successful", $cmdoutput_str.$exec_result."","normal");
			echo 'Ok';
		else:
			zfsnotify( "ZFS Create", "Creation of dataset ".$zfs_cparams['zpool']."/".$zfs_cparams['name']." failed, return code (".$ret.")", $cmdoutput_str.$exec_result."","warning");
			echo $exec_result;
		endif;
			
		# chgrp users <mountpoint>
		# chown nobody <mountpoint>*/

		break;
	case 'updatedataset':
		$cmd_line = createZFSUpdateDatasetCMDLine($_POST['data']).$boutput_str;

		$ret = execCommand($cmd_line, $exec_result);

		if ($ret == 0):
			zfsnotify( "ZFS Update", "Dataset update successful", $cmdoutput_str.$exec_result."","normal");
			echo 'Ok';
		else:
			zfsnotify( "ZFS Update", "Dataset update fail, return code (".$ret.")", $cmdoutput_str.$exec_result."","warning");
			echo $exec_result;
		endif;

		$cmd_line = createZFSInheritDatasetCMDLine($_POST['data']).$boutput_str;

		if ($cmd_line == '' || $ret != 0):
			break;
		endif;

		$ret = execCommand($cmd_line, $exec_result);

		if ($ret != 0):
			zfsnotify( "ZFS Update", "Dataset update partially failed, return code (".$ret.")", $cmdoutput_str.$exec_result."","warning");
			echo $exec_result;
		endif;

		break;
	case 'renamedataset':
		$force = ($_POST['force'] == '1') ? '-f ' : '';
		$cmd_line = 'zfs rename '.$force.escapeshellarg($_POST['data'])." ".escapeshellarg($_POST["newname"]).$boutput_str;

		$ret = execCommand($cmd_line, $exec_result);
		
		if ($ret == 0):
			zfsnotify( "ZFS Rename ", "Dataset ".$_POST['data']." renamed successfully to ".$_POST['newname'], $cmdoutput_str.$exec_result."","normal");
			echo 'Ok';
		else:
			zfsnotify( "ZFS Rename", "Unable to rename dataset ".$_POST['data'].", return code (".$ret.")", $cmdoutput_str.$exec_result."","warning");
			echo $exec_result;
		endif;

		break;
	case 'destroydataset':
		$ret = destroyDataset($_POST['zdataset'], $_POST['force']);

		returnAnswer($ret, "ZFS Dataset Destroy", "Dataset destroyed successfully", "Unable to destroy dataset", true);

		break;
	case 'lockdataset':
		$ret = lockDataset($_POST['zdataset']);
		
		returnAnswer($ret, "ZFS Dataset Lock", "Dataset Locked successfully", "Unable to Lock dataset", true);

		break;
	case 'unlockdataset':
		$ret = unlockDataset($_POST['zdataset'], $_POST['passphrase']);
		
		returnAnswer($ret, "ZFS Dataset Unlock", "Dataset Unlocked successfully", "Unable to Unlock dataset", true);
		
		break;
	case 'promotedataset':
		$ret = promoteDataset($_POST['zdataset'], 0);

		returnAnswer($ret, "ZFS Dataset Promote", "Dataset promoted successfully", "Unable to promote dataset", true);
		
		break;
	case 'rollbacksnapshot':
		$ret = rollbackDatasetSnapshot($_POST['snapshot']);

		returnAnswer($ret, "ZFS Snapshot Rollback", "Snapshot rolled back successfully", "Unable to rollback snapshot", true);

		break;
	case 'holdsnapshot':
		$ret = holdDatasetSnapshot($_POST['snapshot']);

		returnAnswer($ret, "ZFS Snapshot Reference", "Snapshot reference added successfully", "Unable to add reference", false);

		break;
	case 'releasesnapshot':
		$ret = releaseDatasetSnapshot($_POST['snapshot']);

		returnAnswer($ret, "ZFS Snapshot Release", "Snapshot reference removed successfully", "Unable to remove reference", false);

		break;
	case 'clonesnapshot':
		$ret = cloneDatasetSnapshot($_POST['snapshot'], $_POST['clone']);

		returnAnswer($ret, "ZFS Snapshot Clone", "Snapshot cloned successfully", "Unable to clone snapshot", true);

		break;
	case 'destroysnapshot':
		$ret = destroyDataset($_POST['snapshot'], 0);

		returnAnswer($ret, "ZFS Snapshot Destroy", "Snapshot destroyed successfully", "Unable to destroy snapshot", true);

		break;
	case 'snapshotdataset':
		$snapshot = $zfsm_cfg['snap_prefix'].date($zfsm_cfg['snap_pattern']);

		$ret = createDatasetSnapshot( $_POST['zdataset'], $snapshot, $_POST['recursive']);

		returnAnswer($ret, "ZFS Snapshot Create", "Snapshot created successfully", "Unable to create snapshot", true);

		break;
	default:
		echo 'unknown command';
		break;
}

?>
