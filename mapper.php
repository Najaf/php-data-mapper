<?php
  /**
   * Abstract Mapper Superclass
   *
   * Provides database access methods for subclasses
   */
  abstract class Mapper {
    abstract function table_name();
    abstract function do_create_object( $array );
    abstract static function instance();

    private $db; //MySQLi instance
    private $table_fields;

    /**
     * Caches all query results as 'query' => 'result'
     */
    private $query_cache = array();

    function __construct() {
      $this->db = new MySQLi( $host, $user, $pass, $db ); 
      $this->init_table_fields();
      $this->query_cache = array( 'init' => 'init' );
    }

    /**
     * Initializes $this->fields to contain list of table fields
     */
    private function init_table_fields() {
      $result = $this->query( "explain {$this->table_name()}" ) ; 
      $fields = array();
      while( $row = $result->fetch_assoc() ) {
        if( $row['Field'] == 'id' ) { continue; }
        $fields[] = $row['Field'];
      }
      $this->table_fields = $fields;
    }


    /**
     * Reader for $this->fields
     */
    function table_fields() {
      return $this->table_fields;
    }

    /**
     * Run a query
     *
     * Runs a query against the database, stores the result in a cache
     *
     * @param $query String the query to run
     * @return $result MySQLiResult object or false
     */
    protected function query( $query ) {
      //Logger::query( $query );
      if ( ! array_key_exists( $query, $this->query_cache ) ) {
        $this->query_cache[$query] = $this->db->query( $query );
        return $this->query_cache[$query];
      }
      $result = $this->query_cache[$query];
      $result->data_seek(0);
      return $result;
    }


    /**
     * Database last insert_id
     *
     * @return $insert_id int the last inserted id
     */
    protected function insert_id() {
      return $this->db->insert_id;
    }

    /**
     * Escape string for database 
     *
     * @param $string String the string to be escaped
     * @return $escaped_string the escaped string
     */
    protected function escape_string( $string ) {
      if ( is_object( $string ) ) { $string = $string->to_array(); }
      if ( is_array( $string ) ) { return $this->escape_assoc( $string ); }
      return $this->db->escape_string( $string );
    }

    /**
     * Escape assoc_array for database, preserve keys
     *
     * @param $array assoc_array the array containing the data to escape
     * @return $clean_array assoc_array the arrray of clean data
     */
    protected function escape_assoc( $array ) {
      $clean_data = array();
      foreach( $array as $key => $value ) {
        $clean_data[$key] = $this->escape_string( $value ); 
      }
      return $clean_data;
    }

    /**
     * Gets an id from an object if applicable
     *
     * Allows our functions to use objects/ids interchangably
     *
     * @param $object_or_id mixed either a Model or id string
     * @return $id the id of the object
     */
    private function resolve_id( $object_or_id ) {
      if ( $object_or_id instanceof Model ) {
        return $object_or_id->id();
      }
      return $object_or_id;
    }

    /**
     * Create an object based on list of fields
     *
     * @param $array assoc_array fields => values
     */
    protected function create_object( $array ) {
      $obj = $this->do_create_object( $array );
      return $obj;
    }

    /**
     * Return a single object based on a query
     *
     * @param $query string the query to retrieve the record
     * @return $object Model the object representing the record or null if not found
     */
    protected function fetch_one( $query ) {
      $result = $this->query( $query );
      if ( ! $result || $result->num_rows == 0 ) { return null; }
      return $this->create_object( $result->fetch_assoc() );
    }

    /**
     * Return multiple objects based on a query
     *
     * @param $query string the query to retrieve the records
     * @return $array the array containing the records to return
     */
    protected function fetch_many( $query ) {
      $result = $this->query( $query );
      if ( ! $result || $result->num_rows == 0 ) { return null; }
      $objects = array();
      while ( $row = $result->fetch_assoc() ) {
        $objects[] = $this->create_object( $row );
      }
      return $objects;
    }

    /**
     * Find a row by id
     *
     * @param $id mixed the id of the record to find
     * @return $object Model the found object or null
     */
    function find( $id ) {
      return $this->fetch_one( "select * from {$this->table_name()} where id={$id} limit 1" );
    }

    /**
     * Find all rows
     *
     * @return $array array all rows in the table
     */
    function find_all() {
      return $this->fetch_many( "select * from {$this->table_name()}" );
    }

    /**
     * Find rows with custom sql
     *
     * @where_clause string custom sql
     */
    function find_all_by_sql( $custom_sql ) {
      return $this->fetch_many( "select * from {$this->table_name()} where {$custom_sql}" );
    }

    function find_one_by_sql( $custom_sql ) {
      return $this->fetch_one( "select * from {$this->table_name()} where {$custom_sql} limit 1" );
    }

    /**
     * Find rows with id in given array
     *
     * @param $ids array array of ids
     * @return array array of content objects
     */
    function find_ids( $ids ) {
      if ( count( $ids ) < 2 ) {
        return $this->find( $ids[0] );
      }
      $array = array();
      foreach ( $ids as $id ) {
        $arrays[] = $this->find( $id );
      }
      return $arrays;
    }

    /**
     * Find all rows with a given value for a field
     *
     * @param $field string the field to search on
     * @param $value string the value to search for
     * @return $array array of objects representing matches
     */
    function find_by( $field, $value ) {
      $value = $this->escape_string( $value );
      return $this->fetch_many( "select * from {$this->table_name()} where {$field} = '{$value}'" );
    }

    function find_one_by( $field, $value ) {
      $value = $this->escape_string( $value );
      return $this->fetch_one( "select * from {$this->table_name()} where {$field} = '{$value}' limit 1" );
    }

    /**
     * Delete a given object
     *
     * @param $object_or_id mixed the object or id of the object to be deleted
     */
    function delete( $object_or_id ) {
      $id = $this->escape_string( $this->resolve_id( $object_or_id ) );
      $this->query( "delete from {$this->table_name()} where id='{$id}' limit 1" );
    }

    /**
     * Delete multiple objects with a given value for a field
     *
     * @param $field string the field to search on
     * @param $value string the value to delete for
     */
    function delete_by( $field, $value ) {
      $value = $this->escape_string( $value );
      $this->query( "delete from {$this->table_name()} where {$field} = '{$value}'" );
    }

    function save( Model $object ) {
      if ( $object->id() == '' ) {
        return $this->insert( $object );
      } else {
        return $this->update( $object );
      }
    }

    /**
     * Insert a record in the database
     *
     * @param $object Model object representing data to insert
     */
    protected function insert( Model $object ) {
      $data = $this->escape_assoc( $object->to_array() );
      $fields = $this->table_fields();

      //generate query
      $query = "insert into {$this->table_name()} ( ";

      foreach ( $fields as $field ) {
        $query .= "{$field}, ";
      }

      //remove the last pesky comma
      $query = chop( $query );
      $query = substr( $query, 0, strlen( $query ) - 1 );

      $query .= ' ) values ( ';

      foreach( $fields as $field ) {
        $query .= "'{$data[$field]}', ";
      }

      //remove the last pesky comma, again
      $query = chop( $query );
      $query = substr( $query, 0, strlen( $query ) - 1 );

      $query .= ' )';
      $this->query( $query );
      $object->id( $this->insert_id() );
      return $object;
    }

    /**
     * Update a record in the database
     *
     * @param $object Model object representing data to update
     */
    protected function update( Model $object ) {
      $data = $this->escape_assoc( $object->to_array() );
      $query = "update {$this->table_name()} set ";

      $fields = $this->table_fields();

      foreach ( $fields as $field ) {
        $query .= "{$field} = '{$data[$field]}', ";
      }

      //remove the last pesky comma
      $query = chop( $query );
      $query = substr( $query, 0, strlen( $query ) - 1 );

      $query .= " where id='{$data['id']}' limit 1";
      $this->query( $query );
      return $object;
    }
  }
