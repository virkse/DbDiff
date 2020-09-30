<?php

class DbDiff {
    
    ## Array db configturations
    private $config = [
        ## dev server
        "dev" => [
            'host'		=> 'xrmdev.cu0opweblawk.us-east-2.rds.amazonaws.com',
			'user'		=> 'xrmdev',
			'password'	=> 'I4HTjIWhCD24qmkUEpha',
			'name'		=> 'omniweb_dev'
        ],
        "prod" => [
            'host'		=> 'xrmprod.cu0opweblawk.us-east-2.rds.amazonaws.com',
	 		'user'		=> 'xrmprod',
	 		'password'	=> 'XTpiY5Qe0OuCAbWUDg27',
	 		'name'		=> 'omniweb_prod'
        ]
    ];

    ## following are the schema holders for both server's databases
    private $schema_dev = null;
    private $schema_prod = null;


    public function __construct()
    {
        $this->processSchema($this->config);
    }

    private function processSchema($config)
    {
        if(count($config) < 2) {
            die("Schema is not provided properly.");
        }
        foreach($config as $server => $conf) {
            $tables = [];
            $db = mysqli_connect($conf['host'], $conf['user'], $conf['password']);
            if (!$db) {
                return die("Connection with " . $server . " is not completed.");
            }

            ## select database
            if (!mysqli_select_db($db, $conf['name'])) {
                return null;
            }
    
            ## get the tables
            $result = mysqli_query($db, "SHOW TABLES");
            while ($row = mysqli_fetch_row($result)) {
                $tables[$row[0]] = array();
            }
    
            ## itterate on tables and get other attributes of field or column level
            foreach ($tables as $table_name => $fields) {
                $result = mysqli_query($db, "SHOW COLUMNS FROM `" . $table_name . "`");
                if($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $tables[$table_name][$row['Field']] = $row;
                    }
                }
            }
    
    
            $data = array(
                'name' => $server,
                'time' => time(),
                'tables' => $tables
            );
            if($server == "dev") {
                $this->schema_dev = $data;
            }

            if($server == "prod") {
                $this->schema_prod = $data;
            }
            mysqli_close($db);
        }
    }

    public function migrate($should_deploy_too=0)
    {
        $schema_dev_tables = array_keys($this->schema_dev['tables']);
        $schema_prod_tables = array_keys($this->schema_prod['tables']);

        ## set the tables unique
        $tables = array_unique(array_merge($schema_dev_tables, $schema_prod_tables));
        $migrations = [];

        foreach ($tables as $table_name) {
            ## if table is not exists on prod the create table schema
            if (!isset($this->schema_dev['tables'][$table_name])) {
                $table_schema = $this->dropTableSchema($this->config['prod']['name'], $table_name);
                $migrations[] = $table_schema;
                continue;
            }

            ## reverse check if table deleted from the dev then prepare schema for prod
            if (!isset($this->schema_prod['tables'][$table_name])) {
                $table_schema = $this->createTableSchema($table_name, $this->schema_dev['tables'][$table_name]);
                $migrations[] = $table_schema;
                continue;
            }

            ## Check fields exist in both tables
            if(array_key_exists($table_name, $this->schema_prod['tables'])) {
                $fields = array_merge($this->schema_dev['tables'][$table_name], $this->schema_prod['tables'][$table_name]);
                $previsous_field_name = false;
                foreach ($fields as $field_name => $field) {
                    
                    if (!isset($this->schema_dev['tables'][$table_name][$field_name])) {
                        $create_field_schema = $this->dropFieldSchema($table_name, $field_name);
                        $migrations[] = $create_field_schema;
                        continue;
                    }                

                    if (!isset($this->schema_prod['tables'][$table_name][$field_name])) {
                        $create_field_schema = $this->createFieldSchema($table_name, $field_name, $field, $previsous_field_name);
                        $migrations[] = $create_field_schema;
                        continue;
                    }

                    $previsous_field_name = $field_name;                
                }
            }
        }
        ## migrate to production if > 0
        if($should_deploy_too > 0) {
            $db = mysqli_connect($this->config['prod']['host'], $this->config['prod']['user'], $this->config['prod']['password']);
            if (!$db) {
                return die("Connection with " . $server . " is not done on migration.");
            }

            ## select database
            if (!mysqli_select_db($db, $this->config['prod']['name'])) {
                return null;
            }
            foreach($migrations as $query) {
                $result = mysqli_query($db, $query);
            }
            
        }
        return $migrations;
    }

    private function createTableSchema($table, $fields)
    {
        $primary_key = false;
        $field_parts = [];
        $schema = 'CREATE TABLE `'.$table.'` (';
            foreach($fields as $field => $attr) {
                $field_schema = '';
                $field_schema .= ' `'. $field .'` '. strtoupper($attr['Type']) .' ';
                ## NULL
                if($attr["Null"] == 'YES') {
                    $field_schema .= 'NULL ';
                } else {
                    $field_schema .= 'NOT NULL ';
                }

                ## DEFAULT
                if($attr['Default']) {
                    if(is_string($attr["Default"])) {
                        $field_schema .= "DEFAULT '".$attr["Default"]. "'";
                    } else {
                        $field_schema .= "DEFAULT ".$attr["Default"];
                    }
                }
                
                ## extra
                if($attr['Extra'] !== "") {
                    $field_schema .= strtoupper($attr['Extra']);
                    $primary_key = ', PRIMARY KEY (`'. $field .'`)';
                }
                $field_parts[] = $field_schema;
            }
            ## if set primary key
            $schema .= implode(",", $field_parts);
            if($primary_key !== false) $schema .= $primary_key;
        $schema .= ')';
        $schema .= ';';
        return $schema;
    }

    private function createFieldSchema($table, $field, $attr, $after)
    {
        $schema = 'ALTER TABLE ' . '`' . $table . '` ADD `' . $field . '`' . $attr["Type"] . ' ';
        ## NULL
        if($attr["Null"] == 'YES') {
            $schema .= 'NULL ';
        } else {
            $schema .= 'NOT NULL ';
        }

        ## DEFAULT
        if($attr['Default']) {
            if(is_string($attr["Default"])) {
                $schema .= "DEFAULT '".$attr["Default"]. "'";
            } else {
                $schema .= "DEFAULT ".$attr["Default"];
            }
        }

        ## field create after field
        if($after) {
            $schema .= " AFTER `".$after ."`";
        }

        $schema .= ';';
        return $schema;
    }

    private function dropFieldSchema($table, $field)
    {
        return 'ALTER TABLE `'.$table .'` DROP COLUMN `'. $field .'`;';
    }

    private function dropTableSchema($db, $table)
    {
        return 'DROP TABLE `'. $db .'`.`'.$table .'` ;';
    }

}