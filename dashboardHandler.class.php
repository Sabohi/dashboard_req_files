<?php
	require_once("/var/www/html/CZCRM/configs/config.php");
	require_once("/var/www/html/CZCRM/"._MODULE_PATH."DATABASE/DatabaseManageri.php");
	require_once("/var/www/html/CZCRM/"._MODULE_PATH."DATABASE/database_config.php");
	require_once("/var/www/html/CZCRM/"._MODULE_PATH."FUNCTIONS/functions.php");
	require_once ("/var/www/html/CZCRM/classes/function_log.class.php");

	class dashboardHandler extends DATABASE_MANAGER{
		private $DB, $DB_H,$client_id,$REDIS_SERVER1, $REDIS_PORT1, $REDIS_PASS1, $REDIS_SERVER2, $REDIS_PORT2, $REDIS_PASS2,$redis;	

		function __construct($client_id=""){
			$filecontent =file_get_contents("/var/www/html/CZCRM/configs/config.txt");
			$fileArr = json_decode($filecontent,true);
			
			$this->REDIS_SERVER1	=	$fileArr["REDIS_SERVER1"];
			$this->REDIS_PORT1      =	$fileArr["REDIS_PORT1"];
			$this->REDIS_PASS1      =	$fileArr["REDIS_PASS1"];
			$this->REDIS_SERVER2    =	$fileArr["REDIS_SERVER2"];
			$this->REDIS_PORT2      =	$fileArr["REDIS_PORT2"];
			$this->REDIS_PASS2      =	$fileArr["REDIS_PASS2"];


			$dashboardApi = "/var/www/html/node_services/dashboardApi.json";
			$dashboardApiContents = file_get_contents($dashboardApi);
			$dashboardApiArr = json_decode($dashboardApiContents, true);
			$this->K1_PREFIX = isset($dashboardApiArr['K1_PREFIX'])?$dashboardApiArr['K1_PREFIX']:'';
			$this->K2_PREFIX = isset($dashboardApiArr['K2_PREFIX'])?$dashboardApiArr['K2_PREFIX']:'';

			$this->redisConnection(); //create connection with Redis DB

			$this->client_id = $client_id;
			$db_name=($this->client_id==0)?GDB_NAME:DB_PREFIX.$client_id;
			parent::__construct(DB_HOST, DB_USERNAME, DB_PASSWORD,$db_name);
			$this->DB_H = $this->CONNECT(); //create connection with MySQL DB   
		} 
		
		// Function  for creating redeis connection
		private function redisConnection(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","In File", "dashboardHandler.class.php");
			$FLP->prepare_log("1","In Function", "redisConnection");
		
			try{
				$FLP->prepare_log('1','Inside','try');
				if($this->connection = fsockopen($this->REDIS_SERVER1,$this->REDIS_PORT1, $errorNo, $errorStr )){
					if( $errorNo ){
						$FLP->prepare_log("1","Error", "Socket cannot be opened 1");
						throw new RedisException("Socket cannot be opened");
					}else{
						$this->redis = new Redis();
						$this->redis->connect($this->REDIS_SERVER1,$this->REDIS_PORT1);
						if($this->REDIS_PASS1)
						{
							$this->redis->auth($this->REDIS_PASS1);
						}
						$FLP->prepare_log("1","Msg", "Connection created with redis server 1");
					}
				}
				else if($this->connection = fsockopen( $this->REDIS_SERVER2,$this->REDIS_PORT2, $errorNo1, $errorStr)){
					if( $errorNo1 ){
						$FLP->prepare_log("1","Error", "Socket cannot be opened 2");
						throw new RedisException("Socket cannot be opened");
					}else{
						$this->redis = new Redis();
						$this->redis->connect($this->REDIS_SERVER2, $this->PORT2);
						if($this->REDIS_PASS2)
						{
							$this->redis->auth($this->REDIS_PASS2);
						}
						$FLP->prepare_log("1","Msg", "Connection created with redis server 2");
					}
				}
			}catch( Exception $e ){
				$FLP->prepare_log("1","Inside", "catch");
				$FLP->prepare_log("1","Exception", $e);
				echo $e -> getMessage( );
			}
			return $this->connection;
		}

		// Function to push dashboard data in redis list 
		public function pushDashboardDataToRedisList($value=""){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log('1','In File', 'dashboardHandler.class.php');
			$FLP->prepare_log('1','Inside Function','pushDashboardDataToRedisList');
			// $key = "#DASHBOARD#".$this->client_id."#DASHBOARD#";

			$FLP->prepare_log('1','Data received',$value);
			$todays_date = date("Y_m_d");
			$key = $this->K1_PREFIX.$todays_date."_".$this->client_id;
			$FLP->prepare_log('1','key',$key);
			$FLP->prepare_log('1','value',$value);
			if($this->connection){
				$FLP->prepare_log('1','Success','Connection found');
				$this->redis->lpush($key, $value); 
			}
			else{
				$FLP->prepare_log('1','Error','Connection Issue');
				print "Connection Issue";
			}
		}

		// Function to push dashboard data in redis hash 
		public function pushDashboardDataToRedisHash($hashName,$key,$value=""){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log('1','In File', 'dashboardHandler.class.php');
			$FLP->prepare_log('1','Inside Function','pushDashboardDataToRedisHash');

			$FLP->prepare_log('1','Data received',$value);
			// $todays_date = date("Y_m_d");
			
			// $hashName = "crm_dashboard_".$todays_date."_".$this->client_id;
			
			$FLP->prepare_log('1','hashName',$hashName);
			$FLP->prepare_log('1','key',$key);
			$FLP->prepare_log('1','value',$value);
			if($this->connection){
				$FLP->prepare_log('1','Success','Connection found');
				$this->redis->hset($hashName, $key, $value); 
			}
			else{
				$FLP->prepare_log('1','Error','Connection Issue');
				print "Connection Issue";
			}
		}

		// Function to get dashboard data from redis hash 
		public function getDashboardDataFromRedisHash($hashName,$key){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log('1','In File', 'dashboardHandler.class.php');
			$FLP->prepare_log('1','Inside Function','getDashboardDataFromRedisHash');

			// $todays_date = date("Y_m_d");
			// $hashName = "crm_dashboard_".$todays_date."_".$this->client_id;
			
			$FLP->prepare_log('1','hashName',$hashName);
			$FLP->prepare_log('1','key',$key);

			$value = 0;
			if($this->connection){
				$FLP->prepare_log('1','Success','Connection found');
				$value = $this->redis->hget($hashName, $key); 
				$value = (isset($value) && !empty($value))?$value:0;
				$FLP->prepare_log('1','value from redis',$value);
			}
			else{
				$FLP->prepare_log('1','Error','Connection Issue');
				print "Connection Issue";
			}
			return $value;
		}

		//Function for getting ticket/lead's component info
		public function getTicketLeadsInfo($ticketLeadJson){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getTicketLeadsInfo");
			
			$ticketLeadArr=json_decode($ticketLeadJson,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$ticketLeadJson);

			$infoRequired = isset($ticketLeadArr['infoRequired'])?$ticketLeadArr['infoRequired']:"";
			$timePeriod = isset($ticketLeadArr['timePeriod'])?$ticketLeadArr['timePeriod']:"";

			if(empty($infoRequired) || empty($timePeriod)){
				return $this->result = '{"status":"error","message":"Required parameters missing"}';
			}
			else {
				$end_date = time();
				$date_format = date("Y-m-d");

				$date = '';
				switch($timePeriod){
					case 'today':
					break;
					case 'yesterday':
						$yesterday = date("m/d/Y",strtotime("-1 day"));
						$date = strtotime($yesterday);
					break;
					case 'lastweek':
						$lastweek = date("m/d/Y",strtotime("-1 week"));
						$date = strtotime($lastweek);
					break;
					case 'lastmonth':
						$lastmonth = date("m/d/Y",strtotime("-1 month"));
						$date = strtotime($lastmonth);
					break;
				}

				$hashName = $this->K2_PREFIX.date('Y_m_d')."_".$client_id;

				$FLP->prepare_log("1","hashName",$hashName);

				switch($infoRequired){
					case 'ticketStatsData':
						$FLP->prepare_log("1","CASE","ticketStatsData");

						$totalTicketsCreated = $totalTicketsClosed = $totalTicketsClosedPercent = $totalTicketsEscalated = $totalTicketsEscalatedPercent = 0;

						if($timePeriod == 'today'){		
							$totalTicketsCreated = $this->getDashboardDataFromRedisHash($hashName,'ticket_created');
							$FLP->prepare_log("1","totalTicketsCreated",$totalTicketsCreated);
							$totalTicketsClosed = $this-> getDashboardDataFromRedisHash($hashName,'ticket_closed');
							$FLP->prepare_log("1","totalTicketsClosed",$totalTicketsClosed);
							$totalTicketsEscalated = $this-> getDashboardDataFromRedisHash($hashName,'ticket_escalated');
							$FLP->prepare_log("1","totalTicketsEscalated",$totalTicketsEscalated);
						}else{
							
							$query_data_ticket_created = "select COUNT(ticket_id) as count from ticket_details where created_on_unix between '".$date."%' and '".$end_date."%'";
							
							$exe_data_ticket_created = $this->EXECUTE_QUERY($query_data_ticket_created,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_ticket_created===========",$this->getLastQuery());
							$fetch_data_ticket_created = $this->FETCH_ARRAY($exe_data_ticket_created, MYSQLI_ASSOC);
							$totalTicketsCreated = isset($fetch_data_ticket_created['count'])?$fetch_data_ticket_created['count']:0;

							$query_data_ticket_closed = "select COUNT(ticket_id) as count from ticket_details where ticket_status_id = 2 and created_on_unix between '".$date."%' and '".$end_date."%'";
							
							$exe_data_ticket_closed = $this->EXECUTE_QUERY($query_data_ticket_closed,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_ticket_closed===========",$this->getLastQuery());
							$fetch_data_ticket_closed = $this->FETCH_ARRAY($exe_data_ticket_closed, MYSQLI_ASSOC);
							$totalTicketsClosed = isset($fetch_data_ticket_closed['count'])?$fetch_data_ticket_closed['count']:0;

							$query_data = "select COUNT(DISTINCT ticket_id) as count from escalation_rule_execution where type = 'T' and UNIX_TIMESTAMP(rule_execution_date) between '".$date."%' and '".$end_date."%'";

							$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
							$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
							$fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC);
							$totalTicketsEscalated = isset($fetch_data['count'])?$fetch_data['count']:0;
						}
						
						$totalTicketsClosedPercent = ($totalTicketsCreated != 0)?(($totalTicketsClosed/$totalTicketsCreated)*100):0;
						$totalTicketsClosedPercent = round($totalTicketsClosedPercent, 2);
						
						// if(empty($date)){
						// 	$query_data = "select COUNT(DISTINCT ticket_id) as count from escalation_rule_execution where type = 'T' and rule_execution_date like '".$date_format."%'";
							
						// }else{
							// $query_data = "select COUNT(DISTINCT ticket_id) as count from escalation_rule_execution where type = 'T' and UNIX_TIMESTAMP(rule_execution_date) between '".$date."%' and '".$end_date."%'";
						// }

						$totalTicketsEscalatedPercent = ($totalTicketsCreated != 0)?(($totalTicketsEscalated/$totalTicketsCreated)*100):0;

						$totalTicketsEscalatedPercent = round($totalTicketsEscalatedPercent, 2);

						return $this->result = '{"status":"success","totalTicketsCreated":"'.$totalTicketsCreated.'","totalTicketsClosed":"'.$totalTicketsClosed.'","totalTicketsClosedPercent":"'.$totalTicketsClosedPercent.'","totalTicketsEscalated":"'.$totalTicketsEscalated.'","totalTicketsEscalatedPercent":"'.$totalTicketsEscalatedPercent.'"}';
					
					break;
					case 'leadStatsData':
						$totalLeadsCreated = $totalLeadsClosed = $totalLeadsClosedPercent = $totalLeadsEscalated = $totalLeadsEscalatedPercent = 0;

						if($timePeriod == 'today'){		
							$totalLeadsCreated = $this->getDashboardDataFromRedisHash($hashName,'lead_created');
							$totalLeadsClosed = $this->getDashboardDataFromRedisHash($hashName,'lead_closed');
							$totalLeadsEscalated = $this->getDashboardDataFromRedisHash($hashName,'lead_escalated');
						}else{
							$query_data_lead_created = "select COUNT(lead_id) as count from lead_details where created_on_unix between '".$date."%' and '".$end_date."%'";
							
							$exe_data_lead_created = $this->EXECUTE_QUERY($query_data_lead_created,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_lead_created===========",$this->getLastQuery());
							$fetch_data_lead_created = $this->FETCH_ARRAY($exe_data_lead_created, MYSQLI_ASSOC);
							$totalLeadsCreated = isset($fetch_data_lead_created['count'])?$fetch_data_lead_created['count']:0;

							$query_data_lead_closed = "select COUNT(lead_id) as count from lead_details where lead_status_id = 2 and created_on_unix between '".$date."%' and '".$end_date."%'";
							
							$exe_data_lead_closed = $this->EXECUTE_QUERY($query_data_lead_closed,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_lead_closed===========",$this->getLastQuery());
							$fetch_data_lead_closed = $this->FETCH_ARRAY($exe_data_lead_closed, MYSQLI_ASSOC);
							$totalLeadsClosed = isset($fetch_data_lead_closed['count'])?$fetch_data_lead_closed['count']:0;

							$query_data = "select COUNT(DISTINCT ticket_id) as count from escalation_rule_execution where type = 'L' and UNIX_TIMESTAMP(rule_execution_date) between '".$date."%' and '".$end_date."%'";
						
							$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
							$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
							$fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC);
							$totalLeadsEscalated = isset($fetch_data['count'])?$fetch_data['count']:0;
						}

						$totalLeadsClosed = ($totalLeadsCreated != 0)?(($totalLeadsClosed/$totalLeadsCreated)*100):0;
						$totalLeadsClosedPercent = round($totalLeadsClosedPercent, 2);
						
						// if(empty($date)){
						// 	$query_data = "select COUNT(DISTINCT ticket_id) as count from escalation_rule_execution where type = 'L' and rule_execution_date like '".$date_format."%'";
						// }else{
							// $query_data = "select COUNT(DISTINCT ticket_id) as count from escalation_rule_execution where type = 'L' and UNIX_TIMESTAMP(rule_execution_date) between '".$date."%' and '".$end_date."%'";
						// }
					
						$totalLeadsEscalatedPercent = ($totalLeadsEscalated != 0)?(($totalTicketsEscalated/$totalTicketsCreated)*100):0;

						$totalLeadsEscalatedPercent = round($totalLeadsEscalatedPercent, 2);

						return $this->result = '{"status":"success","totalLeadsCreated":"'.$totalLeadsCreated.'","totalLeadsClosed":"'.$totalLeadsClosed.'","totalLeadsClosed":"'.$totalLeadsClosed.'","totalLeadsEscalated":"'.$totalLeadsEscalated.'","totalLeadsEscalatedPercent":"'.$totalLeadsEscalatedPercent.'"}';
					break;
				}
			}				
		}

		//Function for getting task's component info
		public function getTasksInfo($taskJson){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getTasksInfo");
			
			$taskArr=json_decode($taskJson,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$taskJson);

			$infoRequired = isset($taskArr['infoRequired'])?$taskArr['infoRequired']:"";
			$timePeriod = isset($taskArr['timePeriod'])?$taskArr['timePeriod']:"";

			if(empty($infoRequired) || empty($timePeriod)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$end_date = time();
			
				$date = '';
				switch($timePeriod){
					case 'today':
						$today = date("m/d/Y");
						$date = strtotime($today);
					break;
					case 'yesterday':
						$yesterday = date("m/d/Y",strtotime("-1 day"));
						$date = strtotime($yesterday);
					break;
					case 'lastweek':
						$lastweek = date("m/d/Y",strtotime("-1 week"));
						$date = strtotime($lastweek);
					break;
					case 'lastmonth':
						$lastmonth = date("m/d/Y",strtotime("-1 month"));
						$date = strtotime($lastmonth);
					break;
				}

				$hashName = $this->K2_PREFIX.date('Y_m_d')."_".$client_id;

				$FLP->prepare_log("1","hashName",$hashName);

				switch($infoRequired){
					case 'ticketTaskStatsData':
						$FLP->prepare_log("1","CASE","ticketStatsData");

						$totalTasksCreated = $totalTasksClosed = $totalTasksClosedPercent = $totalTasksOverdue = $totalTasksOverduePercent = 0;

						if($timePeriod == 'today'){		
							$totalTasksCreated = $this->getDashboardDataFromRedisHash($hashName,'ticket_task_created');
							$FLP->prepare_log("1","totalTasksCreated",$totalTasksCreated);
							$totalTasksClosed = $this-> getDashboardDataFromRedisHash($hashName,'ticket_task_closed');
							$FLP->prepare_log("1","totalTasksClosed",$totalTasksClosed);
							$totalTasksOverdue = $this->getDashboardDataFromRedisHash($hashName,'ticket_task_overdue');
							$FLP->prepare_log("1","totalTasksOverdue",$totalTasksOverdue);
						}else{
							
							$query_data_task_created = "select COUNT(task_id) as count from task_details where UNIX_TIMESTAMP(created_on) between '".$date."%' and '".$end_date."%'";
							
							$exe_data_task_created = $this->EXECUTE_QUERY($query_data_task_created,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_task_created===========",$this->getLastQuery());
							$fetch_data_task_created = $this->FETCH_ARRAY($exe_data_task_created, MYSQLI_ASSOC);
							$totalTasksCreated = isset($fetch_data_task_created['count'])?$fetch_data_task_created['count']:0;

							$query_data_task_closed = "select COUNT(ticket_id) as count from ticket_details where ticket_status_id = 2 and created_on_unix between '".$date."%' and '".$end_date."%'";
							
							$exe_data_task_closed = $this->EXECUTE_QUERY($query_data_task_closed,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_task_closed===========",$this->getLastQuery());
							$fetch_data_task_closed = $this->FETCH_ARRAY($exe_data_task_closed, MYSQLI_ASSOC);
							$totalTasksClosed = isset($fetch_data_task_closed['count'])?$fetch_data_task_closed['count']:0;

							$query_data_task_overdue = "select COUNT(task_id) as count from task_details where task_status != 'CLOSED' and UNIX_TIMESTAMP(end_datetime)<='".$date."'";
									
							$exe_data_task_overdue = $this->EXECUTE_QUERY($query_data_task_overdue,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_task_overdue===========",$this->getLastQuery());
							$fetch_data_task_overdue = $this->FETCH_ARRAY($exe_data_task_overdue, MYSQLI_ASSOC);
							$totalTasksOverdue = isset($fetch_data_task_overdue['count'])?$fetch_data_task_overdue['count']:0;
						}
						
						$totalTasksClosedPercent = ($totalTasksCreated != 0)?(($totalTasksClosed/$totalTasksCreated)*100):0;
						$totalTasksClosedPercent = round($totalTasksClosedPercent, 2);

						$totalTasksOverduePercent = ($totalTasksCreated != 0)?(($totalTasksOverdue/$totalTasksCreated)*100):0;
						$totalTasksOverduePercent = round($totalTasksOverduePercent, 2);

						return $this->result = '{"status":"success","totalTasksCreated":"'.$totalTasksCreated.'","totalTasksClosed":"'.$totalTasksClosed.'","totalTasksClosedPercent":"'.$totalTasksClosedPercent.'","totalTasksOverdue":"'.$totalTasksOverdue.'","totalTasksOverduePercent":"'.$totalTasksOverduePercent.'"}';
					break;
					case 'leadTaskStatsData':
						$FLP->prepare_log("1","CASE","leadTaskStatsData");

						$totalTasksCreated = $totalTasksClosed = $totalTasksClosedPercent = $totalTasksOverdue = $totalTasksOverduePercent = 0;

						if($timePeriod == 'today'){		
							$totalTasksCreated = $this->getDashboardDataFromRedisHash($hashName,'lead_task_created');
							$FLP->prepare_log("1","totalTasksCreated",$totalTasksCreated);
							$totalTasksClosed = $this-> getDashboardDataFromRedisHash($hashName,'lead_task_closed');
							$FLP->prepare_log("1","totalTasksClosed",$totalTasksClosed);
							$totalTasksOverdue = $this->getDashboardDataFromRedisHash($hashName,'lead_task_overdue');
							$FLP->prepare_log("1","totalTasksOverdue",$totalTasksOverdue);
						}else{	
							$query_data_task_created = "select COUNT(task_id) as count from lead_task_details where UNIX_TIMESTAMP(created_on) between '".$date."%' and '".$end_date."%'";
							
							$exe_data_task_created = $this->EXECUTE_QUERY($query_data_task_created,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_task_created===========",$this->getLastQuery());
							$fetch_data_task_created = $this->FETCH_ARRAY($exe_data_task_created, MYSQLI_ASSOC);
							$totalTasksCreated = isset($fetch_data_task_created['count'])?$fetch_data_task_created['count']:0;

							$query_data_task_closed = "select COUNT(ticket_id) as count from ticket_details where ticket_status_id = 2 and created_on_unix between '".$date."%' and '".$end_date."%'";
							
							$exe_data_task_closed = $this->EXECUTE_QUERY($query_data_task_closed,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_task_closed===========",$this->getLastQuery());
							$fetch_data_task_closed = $this->FETCH_ARRAY($exe_data_task_closed, MYSQLI_ASSOC);
							$totalTasksClosed = isset($fetch_data_task_closed['count'])?$fetch_data_task_closed['count']:0;

							$query_data_task_overdue = "select COUNT(task_id) as count from lead_task_details where task_status != 'CLOSED' and UNIX_TIMESTAMP(end_datetime)<='".$date."'";
									
							$exe_data_task_overdue = $this->EXECUTE_QUERY($query_data_task_overdue,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_task_overdue===========",$this->getLastQuery());
							$fetch_data_task_overdue = $this->FETCH_ARRAY($exe_data_task_overdue, MYSQLI_ASSOC);
							$totalTasksOverdue = isset($fetch_data_task_overdue['count'])?$fetch_data_task_overdue['count']:0;
						}
						
						$totalTasksClosedPercent = ($totalTasksCreated != 0)?(($totalTasksClosed/$totalTasksCreated)*100):0;
						$totalTasksClosedPercent = round($totalTasksClosedPercent, 2);

						$totalTasksOverduePercent = ($totalTasksCreated != 0)?(($totalTasksOverdue/$totalTasksCreated)*100):0;
						$totalTasksOverduePercent = round($totalTasksOverduePercent, 2);

						return $this->result = '{"status":"success","totalTasksCreated":"'.$totalTasksCreated.'","totalTasksClosed":"'.$totalTasksClosed.'","totalTasksClosedPercent":"'.$totalTasksClosedPercent.'","totalTasksOverdue":"'.$totalTasksOverdue.'","totalTasksOverduePercent":"'.$totalTasksOverduePercent.'"}';
					break;
				}
			}				
		}

		//Function for getting mail's component info
		public function getMailsInfo($mailJson){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getMailsInfo");
			
			$taskArr=json_decode($mailJson,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$mailJson);

			$infoRequired = isset($taskArr['infoRequired'])?$taskArr['infoRequired']:"";
			$timePeriod = isset($taskArr['timePeriod'])?$taskArr['timePeriod']:"";

			if(empty($infoRequired) || empty($timePeriod)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$end_date = time();
			
				$date = '';
				switch($timePeriod){
					case 'today':
						$today = date("m/d/Y");
						$date = strtotime($today);
					break;
					case 'yesterday':
						$yesterday = date("m/d/Y",strtotime("-1 day"));
						$date = strtotime($yesterday);
					break;
					case 'lastweek':
						$lastweek = date("m/d/Y",strtotime("-1 week"));
						$date = strtotime($lastweek);
					break;
					case 'lastmonth':
						$lastmonth = date("m/d/Y",strtotime("-1 month"));
						$date = strtotime($lastmonth);
					break;
				}

				$hashName = $this->K2_PREFIX.date('Y_m_d')."_".$client_id;

				$FLP->prepare_log("1","hashName",$hashName);

				switch($infoRequired){
					case 'ticketTaskStatsData':
						$FLP->prepare_log("1","CASE","ticketStatsData");

						$totalTasksCreated = $totalTasksClosed = $totalTasksClosedPercent = $totalTasksOverdue = $totalTasksOverduePercent = 0;

						if($timePeriod == 'today'){	
							$totalMailsReceived = $this->getDashboardDataFromRedisHash($hashName,'ticket_mails_received');
							$FLP->prepare_log("1","totalMailsReceived",$totalMailsReceived);
							$totalMailsReplied = $this-> getDashboardDataFromRedisHash($hashName,'ticket_mails_replied');
							$FLP->prepare_log("1","totalMailsReplied",$totalMailsReplied);
							$totalFreshMailsReceived = $this->getDashboardDataFromRedisHash($hashName,'fresh_ticket_mails_received');
							$FLP->prepare_log("1","totalFreshMailsReceived",$totalFreshMailsReceived);
							$totalFreshMailsReplied = $this->getDashboardDataFromRedisHash($hashName,'fresh_ticket_mails_replied');
							$FLP->prepare_log("1","totalFreshMailsReplied",$totalFreshMailsReplied);
						}else{
							$query_data_mails_received = "select count(mail_id) as count from mail where flow='IN' and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and ticket_type = 'T';";
							$exe_data_mails_received = $this->EXECUTE_QUERY($query_data_mails_received,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_mails_received===========",$this->getLastQuery());
							$fetch_data_mails_received = $this->FETCH_ARRAY($exe_data_mails_received, MYSQLI_ASSOC);
							$totalMailsReceived = isset($fetch_data_mails_received['count'])?$fetch_data_mails_received['count']:0;

							$query_data_mails_replied = "select count(mail_id) as count from mail where flow='IN' and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and answer_flag=0 and ticket_type = 'T';";
							$exe_data_mails_replied = $this->EXECUTE_QUERY($query_data_mails_replied,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_mails_replied===========",$this->getLastQuery());
							$fetch_data_mails_replied = $this->FETCH_ARRAY($exe_data_mails_replied, MYSQLI_ASSOC);
							$totalMailsReplied = isset($fetch_data_mails_replied['count'])?$fetch_data_mails_replied['count']:0;

							$query_data_fresh_mails_received = "select count(mail_id) as count from mail where flow='IN' and mail_references is NULL and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and ticket_type = 'T';";
							$exe_data_fresh_mails_received = $this->EXECUTE_QUERY($query_data_fresh_mails_received,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_fresh_mails_received===========",$this->getLastQuery());
							$fetch_data_fresh_mails_received = $this->FETCH_ARRAY($exe_data_fresh_mails_received, MYSQLI_ASSOC);
							$totalFreshMailsReceived = isset($fetch_data_fresh_mails_received['count'])?$fetch_data_fresh_mails_received['count']:0;

							$query_data_fresh_mails_replied = "select count(mail_id) as count from mail where flow='IN' and mail_references is NULL and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and answer_flag=0 and ticket_type = 'T';";
							$exe_data_fresh_mails_replied = $this->EXECUTE_QUERY($query_data_fresh_mails_replied,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_fresh_mails_replied===========",$this->getLastQuery());
							$fetch_data_fresh_mails_replied = $this->FETCH_ARRAY($exe_data_fresh_mails_replied, MYSQLI_ASSOC);
							$totalFreshMailsReplied = isset($fetch_data_fresh_mails_replied['count'])?$fetch_data_fresh_mails_replied['count']:0;
						}
						
						$totalMailsRepliedPercent = ($totalMailsReceived != 0)?(($totalMailsReplied/$totalMailsReceived)*100):0;
						$totalMailsRepliedPercent = round($totalMailsRepliedPercent, 2);

						$totalFreshMailsRepliedPercent = ($totalFreshMailsReceived != 0)?(($totalFreshMailsReplied/$totalFreshMailsReceived)*100):0;
						$totalFreshMailsRepliedPercent = round($totalFreshMailsRepliedPercent, 2);

						return $this->result = '{"status":"success","totalMailsReceived":"'.$totalMailsReceived.'","totalMailsReplied":"'.$totalMailsReplied.'","totalMailsRepliedPercent":"'.$totalMailsRepliedPercent.'","totalFreshMailsReceived":"'.$totalFreshMailsReceived.'","totalFreshMailsReplied":"'.$totalFreshMailsReplied.'","totalFreshMailsRepliedPercent":"'.$totalFreshMailsRepliedPercent.'"}';
					break;
					case 'leadTaskStatsData':
						$FLP->prepare_log("1","CASE","leadTaskStatsData");

						$totalTasksCreated = $totalTasksClosed = $totalTasksClosedPercent = $totalTasksOverdue = $totalTasksOverduePercent = 0;

						if($timePeriod == 'today'){		
							$totalMailsReceived = $this->getDashboardDataFromRedisHash($hashName,'lead_mails_received');
							$FLP->prepare_log("1","totalMailsReceived",$totalMailsReceived);
							$totalMailsReplied = $this-> getDashboardDataFromRedisHash($hashName,'lead_mails_replied');
							$FLP->prepare_log("1","totalMailsReplied",$totalMailsReplied);
							$totalFreshMailsReceived = $this->getDashboardDataFromRedisHash($hashName,'fresh_lead_mails_received');
							$FLP->prepare_log("1","totalFreshMailsReceived",$totalFreshMailsReceived);
							$totalFreshMailsReplied = $this->getDashboardDataFromRedisHash($hashName,'fresh_lead_mails_replied');
							$FLP->prepare_log("1","totalFreshMailsReplied",$totalFreshMailsReplied);
						}else{
							$query_data_mails_received = "select count(mail_id) as count from mail where flow='IN' and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and ticket_type = 'L';";
							$exe_data_mails_received = $this->EXECUTE_QUERY($query_data_mails_received,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_mails_received===========",$this->getLastQuery());
							$fetch_data_mails_received = $this->FETCH_ARRAY($exe_data_mails_received, MYSQLI_ASSOC);
							$totalMailsReceived = isset($fetch_data_mails_received['count'])?$fetch_data_mails_received['count']:0;

							$query_data_mails_replied = "select count(mail_id) as count from mail where flow='IN' and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and answer_flag=0 and ticket_type = 'L';";
							$exe_data_mails_replied = $this->EXECUTE_QUERY($query_data_mails_replied,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_mails_replied===========",$this->getLastQuery());
							$fetch_data_mails_replied = $this->FETCH_ARRAY($exe_data_mails_replied, MYSQLI_ASSOC);
							$totalMailsReplied = isset($fetch_data_mails_replied['count'])?$fetch_data_mails_replied['count']:0;

							$query_data_fresh_mails_received = "select count(mail_id) as count from mail where flow='IN' and mail_references is NULL and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and ticket_type = 'L';";
							$exe_data_fresh_mails_received = $this->EXECUTE_QUERY($query_data_fresh_mails_received,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_fresh_mails_received===========",$this->getLastQuery());
							$fetch_data_fresh_mails_received = $this->FETCH_ARRAY($exe_data_fresh_mails_received, MYSQLI_ASSOC);
							$totalFreshMailsReceived = isset($fetch_data_fresh_mails_received['count'])?$fetch_data_fresh_mails_received['count']:0;

							$query_data_fresh_mails_replied = "select count(mail_id) as count from mail where flow='IN' and mail_references is NULL and UNIX_TIMESTAMP(downloading_datetime)>='".$date."' and answer_flag=0 and ticket_type = 'L';";
							$exe_data_fresh_mails_replied = $this->EXECUTE_QUERY($query_data_fresh_mails_replied,$this->DB_H);
							$FLP->prepare_log("1","==========query_data_fresh_mails_replied===========",$this->getLastQuery());
							$fetch_data_fresh_mails_replied = $this->FETCH_ARRAY($exe_data_fresh_mails_replied, MYSQLI_ASSOC);
							$totalFreshMailsReplied = isset($fetch_data_fresh_mails_replied['count'])?$fetch_data_fresh_mails_replied['count']:0;
						}
						
						$totalMailsRepliedPercent = ($totalMailsReceived != 0)?(($totalMailsReplied/$totalMailsReceived)*100):0;
						$totalMailsRepliedPercent = round($totalMailsRepliedPercent, 2);

						$totalFreshMailsRepliedPercent = ($totalFreshMailsReceived != 0)?(($totalFreshMailsReplied/$totalFreshMailsReceived)*100):0;
						$totalFreshMailsRepliedPercent = round($totalFreshMailsRepliedPercent, 2);

						return $this->result = '{"status":"success","totalMailsReceived":"'.$totalMailsReceived.'","totalMailsReplied":"'.$totalMailsReplied.'","totalMailsRepliedPercent":"'.$totalMailsRepliedPercent.'","totalFreshMailsReceived":"'.$totalFreshMailsReceived.'","totalFreshMailsReplied":"'.$totalFreshMailsReplied.'","totalFreshMailsRepliedPercent":"'.$totalFreshMailsRepliedPercent.'"}';
					break;
				}
			}				
		}

		//Function for getting user's component info
		public function getUsersInfo($userJson){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","usersInfo");
			
			$userArr=json_decode($userJson,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$userJson);

			$infoRequired = isset($userArr['infoRequired'])?$userArr['infoRequired']:"";

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$file_users = "/var/www/html/CZCRM/master_data_config/$client_id/users.txt";
				$users_arr=array();
				$FLP->prepare_log("1","==========users file===========",$file_users);

				$count = 0;
				switch($infoRequired){
					case 'ticketUsersInfo':
						$totalTicketUsers = $totalActiveTicketUsers = $totalActiveTicketUsersRate = $totalLoggedinTicketUsers = $totalLockedTicketUsers = $totalLockedTicketUsers = 0;
						if(file_exists($file_users)){
							$FLP->prepare_log("1","==========users file===========",$file_users);
							$users_json = file_get_contents($file_users);
							$users_arr = json_decode($users_json,true);
							$FLP->prepare_log("1","==========users_arr===========",$users_arr);

							// totalTicketUsers
							$req_data1 = isset($users_arr[$client_id]['TICKET']['SEARCH'])?$users_arr[$client_id]['TICKET']['SEARCH']:array();
							$totalTicketUsers = count($req_data1);

							//totalActiveTicketUsers
							$req_data2 = isset($users_arr[$client_id]['TICKET']['ACTIVE_ARRAY'])?$users_arr[$client_id]['TICKET']['ACTIVE_ARRAY']:array();
							$totalActiveTicketUsers = count($req_data2);

							//totalActiveTicketUsersRate
							$totalActiveTicketUsersRate = ($totalTicketUsers != 0)?(($totalActiveTicketUsers/$totalTicketUsers)*100):0;

							$totalActiveTicketUsersRate = round($totalActiveTicketUsersRate, 2);

							// totalLoggedinTicketUsers
							$query_data1 = "select count(id) as count from loggedin_live where (assign_type='ticket' or assign_type='both') and user_id != 1";
							$exe_data1 = $this->EXECUTE_QUERY($query_data1,$this->DB_H);
							$FLP->prepare_log("1","==========query_data1===========",$this->getLastQuery());
							$fetch_data1 = $this->FETCH_ARRAY($exe_data1, MYSQLI_ASSOC);
							$totalLoggedinTicketUsers = isset($fetch_data1['count'])?$fetch_data1['count']:0;

							//totalLockedTicketUsers
							$query_data2 = "select count(user_id) as count from users where attempt_flag=1 and (assign_type='ticket' or assign_type='both') and user_id != 1";
							$exe_data2 = $this->EXECUTE_QUERY($query_data2,$this->DB_H);
							$FLP->prepare_log("1","==========query_data2===========",$this->getLastQuery());
							$fetch_data2 = $this->FETCH_ARRAY($exe_data2, MYSQLI_ASSOC);
							$totalLockedTicketUsers = isset($fetch_data2['count'])?$fetch_data2['count']:0;

							//totalLockedTicketUsersRate
							$totalLockedTicketUsersRate = ($totalTicketUsers != 0)?(($totalLockedTicketUsers/$totalTicketUsers)*100):0;

							$totalLockedTicketUsersRate = round($totalLockedTicketUsersRate, 2);

							return $this->result = '{"status":"success","totalUsers":"'.$totalTicketUsers.'","totalActiveUsers":"'.$totalActiveTicketUsers.'","totalActiveUsersRate":"'.$totalActiveTicketUsersRate.'","totalLoggedinUsers":"'.$totalLoggedinTicketUsers.'","totalLockedUsers":"'.$totalLockedTicketUsers.'","totalLockedUsersRate":"'.$totalLockedTicketUsersRate.'"}';
						}else{
							$FLP->prepare_log("1","==========ERROR==========",'users file not found');
							return $this->result = '{"status":"error"}';
						}
					break;
					case 'leadUsersInfo':
						$totalLeadUsers = $totalActiveLeadUsers = $totalLoggedinLeadUsers = $totalLockedLeadUsers = 0;
						if(file_exists($file_users)){
							$FLP->prepare_log("1","==========users file===========",$file_users);
							$users_json = file_get_contents($file_users);
							$users_arr = json_decode($users_json,true);
							$FLP->prepare_log("1","==========users_arr===========",$users_arr);

							// totalLeadUsers
							$req_data1 = isset($users_arr[$client_id]['LEAD']['SEARCH'])?$users_arr[$client_id]['LEAD']['SEARCH']:array();
							$totalLeadUsers = count($req_data1);

							// totalActiveLeadUsers
							$req_data2 = isset($users_arr[$client_id]['LEAD']['ACTIVE_ARRAY'])?$users_arr[$client_id]['LEAD']['ACTIVE_ARRAY']:array();
							$totalActiveLeadUsers = count($req_data2);

							//totalActiveLeadUsersRate
							$totalActiveLeadUsersRate = ($totalLeadUsers != 0)?(($totalActiveLeadUsers/$totalLeadUsers)*100):0;

							$totalActiveLeadUsersRate = round($totalActiveLeadUsersRate, 2);

							// totalLoggedinLeadUsers
							$query_data1 = "select count(id) as count from loggedin_live where (assign_type='lead' or assign_type='both') and user_id != 1";
							$exe_data1 = $this->EXECUTE_QUERY($query_data1,$this->DB_H);
							$FLP->prepare_log("1","==========query_data1===========",$this->getLastQuery());
							$fetch_data1 = $this->FETCH_ARRAY($exe_data1, MYSQLI_ASSOC);
							$totalLoggedinLeadUsers = isset($fetch_data1['count'])?$fetch_data1['count']:0;

							// totalLockedLeadUsers
							$query_data2 = "select count(user_id) as count from users where attempt_flag=1 and (assign_type='lead' or assign_type='both') and user_id != 1";
							$exe_data2 = $this->EXECUTE_QUERY($query_data2,$this->DB_H);
							$FLP->prepare_log("1","==========query_data2===========",$this->getLastQuery());
							$fetch_data2 = $this->FETCH_ARRAY($exe_data2, MYSQLI_ASSOC);
							$totalLockedLeadUsers = isset($fetch_data2['count'])?$fetch_data2['count']:0;

							//totalLockedLeadUsersRate
							$totalLockedLeadUsersRate = ($totalLeadUsers != 0)?(($totalLockedLeadUsers/$totalLeadUsers)*100):0;

							$totalLockedLeadUsersRate = round($totalLockedLeadUsersRate, 2);

							return $this->result = '{"status":"success","totalUsers":"'.$totalLeadUsers.'","totalActiveUsers":"'.$totalActiveLeadUsers.'","totalActiveUsersRate":"'.$totalActiveLeadUsersRate.'","totalLoggedinUsers":"'.$totalLoggedinLeadUsers.'","totalLockedUsers":"'.$totalLockedLeadUsers.'","totalLockedUsersRate":"'.$totalLockedLeadUsersRate.'"}';
						}else{
							$FLP->prepare_log("1","==========ERROR==========",'users file not found');
							return $this->result = '{"status":"error"}';
						}	
					break;
					case 'totalTicketUsers':
						if(file_exists($file_users)){
							$FLP->prepare_log("1","==========users file===========",$file_users);
							$users_json = file_get_contents($file_users);
							$users_arr = json_decode($users_json,true);

							$req_data = isset($users_arr[$client_id]['TICKET']['SEARCH'])?$users_arr[$client_id]['TICKET']['SEARCH']:array();

							$FLP->prepare_log("1","==========users_arr===========",$users_arr);
							$count = count($req_data);
						}else{
							$FLP->prepare_log("1","==========ERROR==========",'users file not found');
						}
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalLeadUsers':
						if(file_exists($file_users)){
							$FLP->prepare_log("1","==========users file===========",$file_users);
							$users_json = file_get_contents($file_users);
							$users_arr = json_decode($users_json,true);
							
							$req_data = isset($users_arr[$client_id]['LEAD']['SEARCH'])?$users_arr[$client_id]['LEAD']['SEARCH']:array();
							$FLP->prepare_log("1","==========users_arr===========",$users_arr);
							$count = count($req_data);
						}else{
							$FLP->prepare_log("1","==========ERROR==========",'users file not found');
						}
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalActiveTicketUsers':
						if(file_exists($file_users)){
							$FLP->prepare_log("1","==========users file===========",$file_users);
							$users_json = file_get_contents($file_users);
							$users_arr = json_decode($users_json,true);
							
							$req_data = isset($users_arr[$client_id]['TICKET']['ACTIVE_ARRAY'])?$users_arr[$client_id]['TICKET']['ACTIVE_ARRAY']:array();

							$FLP->prepare_log("1","==========users_arr===========",$users_arr);
							$count = count($req_data);
						}else{
							$FLP->prepare_log("1","==========ERROR==========",'users file not found');
						}
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalActiveLeadUsers':
						if(file_exists($file_users)){
							$FLP->prepare_log("1","==========users file===========",$file_users);
							$users_json = file_get_contents($file_users);
							$users_arr = json_decode($users_json,true);
							
							$req_data = isset($users_arr[$client_id]['LEAD']['ACTIVE_ARRAY'])?$users_arr[$client_id]['LEAD']['ACTIVE_ARRAY']:array();

							$FLP->prepare_log("1","==========users_arr===========",$users_arr);
							$count = count($req_data);
						}else{
							$FLP->prepare_log("1","==========ERROR==========",'users file not found');
						}
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalLoggedinTicketUsers':
						$query_data = "select count(id) as count from loggedin_live where (assign_type='ticket' or assign_type='both') and user_id != 1";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
						$fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC);
						$count = isset($fetch_data['count'])?$fetch_data['count']:0;
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalLoggedinLeadUsers':
						$query_data = "select count(id) as count from loggedin_live where (assign_type='lead' or assign_type='both') and user_id != 1";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
						$fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC);
						$count = isset($fetch_data['count'])?$fetch_data['count']:0;
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalLockedTicketUsers':
						$query_data = "select count(user_id) as count from users where attempt_flag=1 and (assign_type='ticket' or assign_type='both') and user_id != 1";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
						$fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC);
						$count = isset($fetch_data['count'])?$fetch_data['count']:0;
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
					case 'totalLockedLeadUsers':
						$query_data = "select count(user_id) as count from users where attempt_flag=1 and (assign_type='lead' or assign_type='both') and user_id != 1";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
						$fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC);
						$count = isset($fetch_data['count'])?$fetch_data['count']:0;
						return $this->result = '{"status":"success","count":"'.$count.'"}';
					break;
				}
			}				
		}

		//Function for getting user's component info
		public function getPriorityInfo($json){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","usersInfo");
			
			$arr = json_decode($json,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$json);

			$infoRequired = isset($arr['infoRequired'])?$arr['infoRequired']:"";
			$timePeriod = isset($arr['timePeriod'])?$arr['timePeriod']:"";


			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","==========here in ===========","else");

				$count = 0;
				switch($infoRequired){
					case 'ticketPriorityInfo':
						$data = array();
					
						// totalLoggedinTicketUsers
						$query_data = "select priority, ticket_count from ticket_priority_wise_summary where status='ACTIVE'";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());

						while($fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC)){
							$name = isset($fetch_data['priority'])?$fetch_data['priority']:'';
							$value = isset($fetch_data['ticket_count'])?intval($fetch_data['ticket_count']):0;

							if(!empty($name)){
								$myObj = new stdClass();
								$myObj->name = $name;
								$myObj->value = $value;
								// $myJSON = json_encode($myObj);
								array_push($data,$myObj);
							}
						}
						$result = array(
								"status" => "success",
								"data"=> $data
						);

						$result = json_encode($result, true);

						return $this->result = $result;
					break;
				}
			}				
		}

		//Function for getting user's component info
		public function getStatusInfo($json){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getStatusInfo");
			
			$arr = json_decode($json,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$json);

			$infoRequired = isset($arr['infoRequired'])?$arr['infoRequired']:"";
			$timePeriod = isset($arr['infoRequired'])?$arr['infoRequired']:"";

			$date = date('Y-m-d');

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","==========here in ===========","else");

				$count = 0;
				switch($infoRequired){
					case 'ticketStatusInfo':
						$tableName = 'ticket_status_wise_summary';
						$fieldName1 = 'ticket_status';
						$fieldName2 = 'ticket_count';
					break;
					case 'leadStatusInfo':
						$tableName = 'lead_status_wise_summary';
						$fieldName1 = 'lead_status';
						$fieldName2 = 'lead_count';
					break;
				}

				if(!empty($tableName) && !empty($fieldName1) && !empty($fieldName2)){
					$data = array();
					
					$query_data = "select $fieldName1, $fieldName2 from $tableName where status='ACTIVE' and entry_date = '$date'";
					$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
					$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());

					while($fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC)){
						$name = isset($fetch_data[$fieldName1])?$fetch_data[$fieldName1]:'';
						$value = (isset($fetch_data[$fieldName2]) && !empty($fetch_data[$fieldName2]))?intval($fetch_data[$fieldName2]):0;

						if(!empty($name)){
							$myObj = new stdClass();
							$myObj->name = $name;
							$myObj->value = $value;
							array_push($data,$myObj);
						}
					}
					$result = array(
							"status" => "success",
							"data"=> $data
					);

					$result = json_encode($result, true);

					return $this->result = $result;
				}else{
					return $this->result = '{"status":"error","message":"Some error occur"}';
				}
			}				
		}

		//Function for getting type's component info
		public function getTypeInfo($json){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getTypeInfo");
			
			$arr = json_decode($json,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$json);

			$infoRequired = isset($arr['infoRequired'])?$arr['infoRequired']:"";
			$timePeriod = isset($arr['timePeriod'])?$arr['timePeriod']:"";

			$date = date('Y-m-d');

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","==========here in ===========","else");

				$count = 0;
				switch($infoRequired){
					case 'ticketTypeInfo':
						$tableName = 'ticket_type_wise_summary';
						$fieldName = 'ticket_type';

						$data = array();
					
						$query_data = "select $fieldName, ticket_count from $tableName where status='ACTIVE' and entry_date = '$date'";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
	
						while($fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC)){
							$name = isset($fetch_data[$fieldName])?$fetch_data[$fieldName]:'';
							$value = (isset($fetch_data['ticket_count']) && !empty($fetch_data['ticket_count']))?intval($fetch_data['ticket_count']):0;
	
							if(!empty($name)){
								$myObj = new stdClass();
								$myObj->name = $name;
								$myObj->value = $value;
								array_push($data,$myObj);
							}
						}
						$result = array(
								"status" => "success",
								"data"=> $data
						);
	
						$result = json_encode($result, true);
	
						return $this->result = $result;
					break;
				}
			}				
		}

		//Function for getting state's component info
		public function getStateInfo($json){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getStateInfo");
			
			$arr = json_decode($json,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$json);

			$infoRequired = isset($arr['infoRequired'])?$arr['infoRequired']:"";
			$timePeriod = isset($arr['timePeriod'])?$arr['timePeriod']:"";

			$date = date('Y-m-d');

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","==========here in ===========","else");

				$count = 0;
				switch($infoRequired){
					case 'leadStateInfo':
						$tableName = 'lead_state_wise_summary';
						$fieldName1 = 'lead_state';
						$fieldName2 = 'lead_count';

						$data = array();
					
						$query_data = "select $fieldName1, $fieldName2 from $tableName where entry_date = '$date'";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
	
						while($fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC)){
							$name = isset($fetch_data[$fieldName1])?$fetch_data[$fieldName1]:'';
							$value = (isset($fetch_data[$fieldName2]) && !empty($fetch_data[$fieldName2]))?intval($fetch_data[$fieldName2]):0;
	
							if(!empty($name)){
								$myObj = new stdClass();
								$myObj->name = $name;
								$myObj->value = $value;
								array_push($data,$myObj);
							}
						}
						$result = array(
								"status" => "success",
								"data"=> $data
						);
	
						$result = json_encode($result, true);
	
						return $this->result = $result;
					break;
				}
			}				
		}

		//Function for getting disposition's component info
		public function getDispositionInfo($json){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","dashboardHandler.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getDispositionInfo");
			
			$arr = json_decode($json,true);
			$FLP->prepare_log("1","=======[Data Recieved]========",$json);

			$infoRequired = isset($arr['infoRequired'])?$arr['infoRequired']:"";
			$timePeriod = isset($arr['timePeriod'])?$arr['timePeriod']:"";

			$date = date('Y-m-d');

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","==========here in ===========","else");

				$count = 0;
				switch($infoRequired){
					case 'leadDispositionInfo':
						$tableName = 'lead_disposition_wise_summary';
						$fieldName1 = 'lead_disposition';
						$fieldName2 = 'lead_count';

						$data = array();
					
						$query_data = "select $fieldName1, $fieldName2 from $tableName where status='ACTIVE' ";
						$exe_data = $this->EXECUTE_QUERY($query_data,$this->DB_H);
						$FLP->prepare_log("1","==========query_data===========",$this->getLastQuery());
	
						while($fetch_data = $this->FETCH_ARRAY($exe_data, MYSQLI_ASSOC)){
							$name = isset($fetch_data[$fieldName])?$fetch_data[$fieldName]:'';
							$value = (isset($fetch_data[$fieldName2]) && !empty($fetch_data[$fieldName2]))?intval($fetch_data[$fieldName2]):0;
	
							if(!empty($name)){
								$myObj = new stdClass();
								$myObj->name = $name;
								$myObj->value = $value;
								array_push($data,$myObj);
							}
						}
						$result = array(
								"status" => "success",
								"data"=> $data
						);
	
						$result = json_encode($result, true);
	
						return $this->result = $result;
					break;
				}
			}				
		}
	}
?>