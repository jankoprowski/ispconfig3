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

class cronjob {

	// default is every 5 minutes
	protected $_schedule = '*/5 * * * *';

	// may a run be skipped?
	protected $_no_skip = false;

	// if true, this job is run when it is first recognized. If false, the next run is calculated from schedule on first run.
	protected $_run_at_new = false;

	protected $_last_run = null;
	protected $_next_run = null;
	private $_running = false;

	/** return schedule */


	public function getSchedule() {
		return $this->_schedule;
	}



	/** run through cronjob sequence **/
	public function run() {

		print "Called run() for class " . get_class($this) . "\n";
		print "Job has schedule: " . $this->_schedule . "\n";
		$this->onPrepare();
		$run_it = $this->onBeforeRun();
		if($run_it == true) {
			$this->onRunJob();
			$this->onAfterRun();
		}
		$this->onCompleted();

		return;
	}

	/* this function prepares some data for the job and sets next run time if first executed */
	protected function onPrepare() {
		global $app;

		print "Called onPrepare() for class " . get_class($this) . "\n";
		// check the run time and values for this job

		// get previous run data
		$data = $app->db->queryOneRecord("SELECT `last_run`, `next_run`, `running` FROM `sys_cron` WHERE `name` = '" . $app->db->quote(get_class($this)) . "'");
		if($data) {
			if($data['last_run']) $this->_last_run = $data['last_run'];
			if($data['next_run']) $this->_next_run = $data['next_run'];
			if($data['running'] == 1) $this->_running = true;
		}
		if(!$this->_next_run) {
			if($this->_run_at_new == true) {
				$this->_next_run = ISPConfigDateTime::dbtime(); // run now.
			} else {
				$app->cron->parseCronLine($this->_schedule);
				$next_run = $app->cron->getNextRun(ISPConfigDateTime::dbtime());
				$this->_next_run = $next_run;

				$app->db->query("REPLACE INTO `sys_cron` (`name`, `last_run`, `next_run`, `running`) VALUES ('" . $app->db->quote(get_class($this)) . "', " . ($this->_last_run ? "'" . $app->db->quote($this->_last_run) . "'" : "NULL") . ", " . ($next_run === false ? "NULL" : "'" . $app->db->quote($next_run) . "'") . ", " . ($this->_running == true ? "1" : "0") . ")");
			}
		}
	}

	/* this function checks if a cron job's next runtime is reached and returns true or false */
	protected function onBeforeRun() {
		global $app;

		print "Called onBeforeRun() for class " . get_class($this) . "\n";

		if($this->_running == true) return false; // job is still marked as running!

		print "Jobs next run is " . $this->_next_run . "\n";
		$reached = ISPConfigDateTime::compare($this->_next_run, ISPConfigDateTime::dbtime());
		print "Date compare of " . ISPConfigDateTime::to_timestamp($this->_next_run) . " and " . ISPConfigDateTime::dbtime() . " is " . $reached . "\n";
		if($reached === false) return false; // error!

		if($reached === -1) {
			// next_run time not reached
			return false;
		}

		// next_run time reached (reached === 0 or -1)

		// calculare next run time based on last_run or current time
		$app->cron->parseCronLine($this->_schedule);
		if($this->_no_skip == true) {
			// we need to calculare the next run based on the previous next_run, as we may not skip one.
			$next_run = $app->cron->getNextRun($this->_next_run);
			if($next_run === false) {
				// we could not calculate next run, try it with current time
				$next_run = $app->cron->getNextRun(ISPConfigDateTime::dbtime());
			}
		} else {
			// calculate next run based on current time
			$next_run = $app->cron->getNextRun(ISPConfigDateTime::dbtime());
		}

		print "Jobs next run is now " . $next_run . "\n";

		$app->db->query("REPLACE INTO `sys_cron` (`name`, `last_run`, `next_run`, `running`) VALUES ('" . $app->db->quote(get_class($this)) . "', NOW(), " . ($next_run === false ? "NULL" : "'" . $app->db->quote($next_run) . "'") . ", 1)");
		return true;
	}

	// child classes should override this!
	protected function onRunJob() {
		global $app;

		print "Called onRun() for class " . get_class($this) . "\n";
	}

	// child classes may override this!
	protected function onAfterRun() {
		global $app;

		print "Called onAfterRun() for class " . get_class($this) . "\n";
	}

	// child classes may NOT override this!
	private function onCompleted() {
		global $app;

		print "Called onCompleted() for class " . get_class($this) . "\n";
		$app->db->query("UPDATE `sys_cron` SET `running` = 0 WHERE `name` = '" . $app->db->quote(get_class($this)) . "'");
	}

}

?>
