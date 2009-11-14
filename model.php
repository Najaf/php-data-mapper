<?php
  abstract class Model {
    protected abstract function mapper();

    protected $fields = array();
    protected $subclass_fields = array();

    function __call( $method, $args ) {
      //provide accessors for database fields
      if (  $this->is_a_field( $method ) ) { 
        if ( isset( $args[0] ) ) {
          $this->fields[$method] = $args[0];
        }
        return $this->fields[$method];
      }
    }

    private function is_a_field( $string ) {
      $fields = $this->mapper()->table_fields();
      $fields[] = 'id';
      $fields = array_merge( $fields, $this->subclass_fields );
      return in_array( $string, $fields );
    }

    private function check_fields_are_valid( $array ) {
      foreach ( $array as $field => $value ) {
        if ( ! $this->is_a_field( $field ) ) {
          return false;
        }
      }
      return true;
    }

    protected function add_field( $string ) {
      $this->subclass_fields[] = $string;
    }

    function set_fields( $array ) {
      if ( ! $this->check_fields_are_valid( $array ) ) {
        throw new Exception( 'Invalid field in Model::set_fields()' );
      }
      $this->fields = $array;
    }

    function to_array() {
      return $this->fields;
    }

    function to_string() {
      $string = get_class( $this ) . "\n";
      foreach ( $this->fields as $field => $value ) {
        $string .= "$field : $value \n";
      }
      return $string;
    }

    function save( ) {
      $this->mapper()->save( $this );
    }

    function save_fields( $fields ) {
      foreach ( $fields as $key => $value ) {
        if ( $this->is_a_field( $key ) && $key != 'id' ) {
          $this->fields[$key] = $value;
        }
      }
      $this->save();
    }
  }
