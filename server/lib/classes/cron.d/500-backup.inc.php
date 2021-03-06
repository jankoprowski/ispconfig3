<?php

/*
Copyright (c) 2013, Marius Cramer, pixcept KG
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of ISPConfig nor the names of its contributors
      may be used to endorse or promote products derived from this software without
      specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class cronjob_backup extends cronjob {

	// job schedule
	protected $_schedule = '0 0 * * *';

	/* this function is optional if it contains no custom code */
	public function onPrepare() {
		global $app;

		parent::onPrepare();
	}

	/* this function is optional if it contains no custom code */
	public function onBeforeRun() {
		global $app;

		return parent::onBeforeRun();
	}

	public function onRunJob() {
		global $app, $conf;

		$server_config = $app->getconf->get_server_config($conf['server_id'], 'server');
		$backup_dir = $server_config['backup_dir'];
		$backup_mode = $server_config['backup_mode'];
		if($backup_mode == '') $backup_mode = 'userzip';

		$web_config = $app->getconf->get_server_config($conf['server_id'], 'web');
		$http_server_user = $web_config['user'];

		if($backup_dir != '') {

			if(isset($server_config['backup_dir_ftpread']) && $server_config['backup_dir_ftpread'] == 'y') {
				$backup_dir_permissions = 0755;
			} else {
				$backup_dir_permissions = 0750;
			}

			if(!is_dir($backup_dir)) {
				mkdir(escapeshellcmd($backup_dir), $backup_dir_permissions, true);
			} else {
				chmod(escapeshellcmd($backup_dir), $backup_dir_permissions);
			}
			
			//* mount backup directory, if necessary
			$run_backups = true;
			$server_config['backup_dir_mount_cmd'] = trim($server_config['backup_dir_mount_cmd']);
			if($server_config['backup_dir_is_mount'] == 'y' && $server_config['backup_dir_mount_cmd'] != ''){
				if(!$app->system->is_mounted($backup_dir)){
					exec(escapeshellcmd($server_config['backup_dir_mount_cmd']));
					sleep(1);
					if(!$app->system->is_mounted($backup_dir)) $run_backups = false;
				}
			}

			if($run_backups){
				//* backup only active domains
				$sql = "SELECT * FROM web_domain WHERE server_id = '".$conf['server_id']."' AND (type = 'vhost' OR type = 'vhostsubdomain' OR type = 'vhostalias') AND active = 'y'";
				$records = $app->db->queryAllRecords($sql);
				if(is_array($records)) {
					foreach($records as $rec) {

						//* Do the website backup
						if($rec['backup_interval'] == 'daily' or ($rec['backup_interval'] == 'weekly' && date('w') == 0) or ($rec['backup_interval'] == 'monthly' && date('d') == '01')) {

							$web_path = $rec['document_root'];
							$web_user = $rec['system_user'];
							$web_group = $rec['system_group'];
							$web_id = $rec['domain_id'];
							$web_backup_dir = $backup_dir.'/web'.$web_id;
							if(!is_dir($web_backup_dir)) mkdir($web_backup_dir, 0750);
							chmod($web_backup_dir, 0750);
							//if(isset($server_config['backup_dir_ftpread']) && $server_config['backup_dir_ftpread'] == 'y') {
							chown($web_backup_dir, $rec['system_user']);
							chgrp($web_backup_dir, $rec['system_group']);
							/*} else {
								chown($web_backup_dir, 'root');
								chgrp($web_backup_dir, 'root');
							}*/
						
							$backup_excludes = '';
							$b_excludes = explode(',', trim($rec['backup_excludes']));
							if(is_array($b_excludes) && !empty($b_excludes)){
								foreach($b_excludes as $b_exclude){
									$b_exclude = trim($b_exclude);
									if($b_exclude != ''){
										$backup_excludes .= ' --exclude='.escapeshellarg($b_exclude);
									}
								}
							}
						
							if($backup_mode == 'userzip') {
								//* Create a .zip backup as web user and include also files owned by apache / nginx user
								$web_backup_file = 'web'.$web_id.'_'.date('Y-m-d_H-i').'.zip';
								exec('cd '.escapeshellarg($web_path).' && sudo -u '.escapeshellarg($web_user).' find . -group '.escapeshellarg($web_group).' -print 2> /dev/null | zip -b /tmp --exclude=backup\*'.$backup_excludes.' --symlinks '.escapeshellarg($web_backup_dir.'/'.$web_backup_file).' -@', $tmp_output, $retval);
								if($retval == 0 || $retval == 12) exec('cd '.escapeshellarg($web_path).' && sudo -u '.escapeshellarg($web_user).' find . -user '.escapeshellarg($http_server_user).' -print 2> /dev/null | zip -b /tmp --exclude=backup\*'.$backup_excludes.' --update --symlinks '.escapeshellarg($web_backup_dir.'/'.$web_backup_file).' -@', $tmp_output, $retval);
							} else {
								//* Create a tar.gz backup as root user
								$web_backup_file = 'web'.$web_id.'_'.date('Y-m-d_H-i').'.tar.gz';
								exec('tar pczf '.escapeshellarg($web_backup_dir.'/'.$web_backup_file).' --exclude=backup\*'.$backup_excludes.' --directory '.escapeshellarg($web_path).' .', $tmp_output, $retval);
							}
							if($retval == 0 || ($backup_mode != 'userzip' && $retval == 1) || ($backup_mode == 'userzip' && $retval == 12)) { // tar can return 1, zip can return 12(due to harmless warings) and still create valid backups  
								if(is_file($web_backup_dir.'/'.$web_backup_file)){
									chown($web_backup_dir.'/'.$web_backup_file, 'root');
									chgrp($web_backup_dir.'/'.$web_backup_file, 'root');
									chmod($web_backup_dir.'/'.$web_backup_file, 0750);

									//* Insert web backup record in database
									//$insert_data = "(server_id,parent_domain_id,backup_type,backup_mode,tstamp,filename) VALUES (".$conf['server_id'].",".$web_id.",'web','".$backup_mode."',".time().",'".$app->db->quote($web_backup_file)."')";
									//$app->dbmaster->datalogInsert('web_backup', $insert_data, 'backup_id');
									$sql = "INSERT INTO web_backup (server_id,parent_domain_id,backup_type,backup_mode,tstamp,filename) VALUES (".$conf['server_id'].",".$web_id.",'web','".$backup_mode."',".time().",'".$app->db->quote($web_backup_file)."')";
									$app->db->query($sql);
									if($app->db->dbHost != $app->dbmaster->dbHost) $app->dbmaster->query($sql);
								}
							} else {
								if(is_file($web_backup_dir.'/'.$web_backup_file)) unlink($web_backup_dir.'/'.$web_backup_file);
							}

							//* Remove old backups
							$backup_copies = intval($rec['backup_copies']);

							$dir_handle = dir($web_backup_dir);
							$files = array();
							while (false !== ($entry = $dir_handle->read())) {
								if($entry != '.' && $entry != '..' && substr($entry, 0, 3) == 'web' && is_file($web_backup_dir.'/'.$entry)) {
									$files[] = $entry;
								}
							}
							$dir_handle->close();

							rsort($files);

							for ($n = $backup_copies; $n <= 10; $n++) {
								if(isset($files[$n]) && is_file($web_backup_dir.'/'.$files[$n])) {
									unlink($web_backup_dir.'/'.$files[$n]);
									//$sql = "SELECT backup_id FROM web_backup WHERE server_id = ".$conf['server_id']." AND parent_domain_id = $web_id AND filename = '".$app->db->quote($files[$n])."'";
									//$tmp = $app->dbmaster->queryOneRecord($sql);
									//$app->dbmaster->datalogDelete('web_backup', 'backup_id', $tmp['backup_id']);
									//$sql = "DELETE FROM web_backup WHERE backup_id = ".intval($tmp['backup_id']);
									$sql = "DELETE FROM web_backup WHERE server_id = ".$conf['server_id']." AND parent_domain_id = $web_id AND filename = '".$app->db->quote($files[$n])."'";
									$app->db->query($sql);
									if($app->db->dbHost != $app->dbmaster->dbHost) $app->dbmaster->query($sql);
								}
							}

							unset($files);
							unset($dir_handle);

							//* Remove backupdir symlink and create as directory instead
							$app->system->web_folder_protection($web_path, false);

							if(is_link($web_path.'/backup')) {
								unlink($web_path.'/backup');
							}
							if(!is_dir($web_path.'/backup')) {
								mkdir($web_path.'/backup');
								chown($web_path.'/backup', $rec['system_user']);
								chgrp($web_path.'/backup', $rec['system_group']);
							}

							$app->system->web_folder_protection($web_path, true);
						}

						/* If backup_interval is set to none and we have a
						backup directory for the website, then remove the backups */
						if($rec['backup_interval'] == 'none' || $rec['backup_interval'] == '') {
							$web_id = $rec['domain_id'];
							$web_user = $rec['system_user'];
							$web_backup_dir = realpath($backup_dir.'/web'.$web_id);
							if(is_dir($web_backup_dir)) {
								exec('sudo -u '.escapeshellarg($web_user).' rm -f '.escapeshellarg($web_backup_dir.'/*'));
								$sql = "DELETE FROM web_backup WHERE server_id = ".intval($conf['server_id'])." AND parent_domain_id = ".intval($web_id);
								$app->db->query($sql);
								if($app->db->dbHost != $app->dbmaster->dbHost) $app->dbmaster->query($sql);
							}
						}
					}
				}

				$sql = "SELECT * FROM web_database WHERE server_id = ".$conf['server_id']." AND backup_interval != 'none' AND backup_interval != ''";
				$records = $app->db->queryAllRecords($sql);
				if(is_array($records)) {

					include 'lib/mysql_clientdb.conf';

					foreach($records as $rec) {

						//* Do the database backup
						if($rec['backup_interval'] == 'daily' or ($rec['backup_interval'] == 'weekly' && date('w') == 0) or ($rec['backup_interval'] == 'monthly' && date('d') == '01')) {

							$web_id = $rec['parent_domain_id'];
							$db_backup_dir = $backup_dir.'/web'.$web_id;
							if(!is_dir($db_backup_dir)) mkdir($db_backup_dir, 0750);
							chmod($db_backup_dir, 0750);
							chown($db_backup_dir, 'root');
							chgrp($db_backup_dir, 'root');

							//* Do the mysql database backup with mysqldump
							$db_id = $rec['database_id'];
							$db_name = $rec['database_name'];
							$db_backup_file = 'db_'.$db_name.'_'.date('Y-m-d_H-i').'.sql';
							//$command = "mysqldump -h '".escapeshellcmd($clientdb_host)."' -u '".escapeshellcmd($clientdb_user)."' -p'".escapeshellcmd($clientdb_password)."' -c --add-drop-table --create-options --quick --result-file='".$db_backup_dir.'/'.$db_backup_file."' '".$db_name."'";
							$command = "mysqldump -h ".escapeshellarg($clientdb_host)." -u ".escapeshellarg($clientdb_user)." -p".escapeshellarg($clientdb_password)." -c --add-drop-table --create-options --quick --result-file='".$db_backup_dir.'/'.$db_backup_file."' '".$db_name."'";
							exec($command, $tmp_output, $retval);

							//* Compress the backup with gzip
							if($retval == 0) exec("gzip -c '".escapeshellcmd($db_backup_dir.'/'.$db_backup_file)."' > '".escapeshellcmd($db_backup_dir.'/'.$db_backup_file).".gz'", $tmp_output, $retval);

							if($retval == 0){
								if(is_file($db_backup_dir.'/'.$db_backup_file.'.gz')){
									chmod($db_backup_dir.'/'.$db_backup_file.'.gz', 0750);
									chown($db_backup_dir.'/'.$db_backup_file.'.gz', fileowner($db_backup_dir));
									chgrp($db_backup_dir.'/'.$db_backup_file.'.gz', filegroup($db_backup_dir));

									//* Insert web backup record in database
									//$insert_data = "(server_id,parent_domain_id,backup_type,backup_mode,tstamp,filename) VALUES (".$conf['server_id'].",$web_id,'mysql','sqlgz',".time().",'".$app->db->quote($db_backup_file).".gz')";
									//$app->dbmaster->datalogInsert('web_backup', $insert_data, 'backup_id');
									$sql = "INSERT INTO web_backup (server_id,parent_domain_id,backup_type,backup_mode,tstamp,filename) VALUES (".$conf['server_id'].",$web_id,'mysql','sqlgz',".time().",'".$app->db->quote($db_backup_file).".gz')";
									$app->db->query($sql);
									if($app->db->dbHost != $app->dbmaster->dbHost) $app->dbmaster->query($sql);
								}
							} else {
								if(is_file($db_backup_dir.'/'.$db_backup_file.'.gz')) unlink($db_backup_dir.'/'.$db_backup_file.'.gz');
							}
							//* Remove the uncompressed file
							if(is_file($db_backup_dir.'/'.$db_backup_file)) unlink($db_backup_dir.'/'.$db_backup_file);

							//* Remove old backups
							$backup_copies = intval($rec['backup_copies']);

							$dir_handle = dir($db_backup_dir);
							$files = array();
							while (false !== ($entry = $dir_handle->read())) {
								if($entry != '.' && $entry != '..' && preg_match('/^db_(.*?)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.sql.gz$/', $entry, $matches) && is_file($db_backup_dir.'/'.$entry)) {
									if(array_key_exists($matches[1], $files) == false) $files[$matches[1]] = array();
									$files[$matches[1]][] = $entry;
								}
							}
							$dir_handle->close();

							reset($files);
							foreach($files as $db_name => $filelist) {
								rsort($filelist);
								for ($n = $backup_copies; $n <= 10; $n++) {
									if(isset($filelist[$n]) && is_file($db_backup_dir.'/'.$filelist[$n])) {
										unlink($db_backup_dir.'/'.$filelist[$n]);
										//$sql = "SELECT backup_id FROM web_backup WHERE server_id = ".$conf['server_id']." AND parent_domain_id = $web_id AND filename = '".$app->db->quote($filelist[$n])."'";
										//$tmp = $app->dbmaster->queryOneRecord($sql);
										//$sql = "DELETE FROM web_backup WHERE backup_id = ".intval($tmp['backup_id']);
										$sql = "DELETE FROM web_backup WHERE server_id = ".$conf['server_id']." AND parent_domain_id = $web_id AND filename = '".$app->db->quote($filelist[$n])."'";
										$app->db->query($sql);
										if($app->db->dbHost != $app->dbmaster->dbHost) $app->dbmaster->query($sql);
									}
								}
							}

							unset($files);
							unset($dir_handle);
						}
					}

					unset($clientdb_host);
					unset($clientdb_user);
					unset($clientdb_password);

				}

				// remove non-existing backups from database
				$backups = $app->db->queryAllRecords("SELECT * FROM web_backup WHERE server_id = ".$conf['server_id']);
				if(is_array($backups) && !empty($backups)){
					foreach($backups as $backup){
						$backup_file = $backup_dir.'/web'.$backup['parent_domain_id'].'/'.$backup['filename'];
						if(!is_file($backup_file)){
							$sql = "DELETE FROM web_backup WHERE server_id = ".$conf['server_id']." AND parent_domain_id = ".$backup['parent_domain_id']." AND filename = '".$backup['filename']."'";
							$app->db->query($sql);
							if($app->db->dbHost != $app->dbmaster->dbHost) $app->dbmaster->query($sql);
						}
					}
				}
			} else {
				//* send email to admin that backup directory could not be mounted
				$global_config = $app->getconf->get_global_config('mail');
				if($global_config['admin_mail'] != ''){
					$subject = 'Backup directory '.$backup_dir.' could not be mounted';
					$message = "Backup directory ".$backup_dir." could not be mounted.\n\nThe command\n\n".$server_config['backup_dir_mount_cmd']."\n\nfailed.";
					mail($global_config['admin_mail'], $subject, $message);
				}
			}
		}

		parent::onRunJob();
	}

	/* this function is optional if it contains no custom code */
	public function onAfterRun() {
		global $app;

		parent::onAfterRun();
	}

}

?>
