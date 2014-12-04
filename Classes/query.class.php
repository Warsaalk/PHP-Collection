<?php
/** 
 * @package PHP-Collection
 * @subpackage Classes\Utils
 * @license http://opensource.org/licenses/mit-license.php
 * @author Klaas Van Parys <https://github.com/Warsaalk>
 */

/**
 * iQuery interface
 */
interface iQuery {
	
	/**
	 * Returns the build query
	 * 
	 * @return string 
	 */
	public function get();

}

interface OrderBy_Query {
    
    const ORDER_DEFAULT = 0;
    const ORDER_DESC = 1;
    const ORDER_ASC = 2;
    
    /**
     * @param string $column
     * @param integer $order
     */
    public function orderBy($column, $order=self::ORDER_DEFAULT);
    
}

class Join_Query implements iQuery {

		/* Join types */
		const JOIN 				= " JOIN";
		//const JOIN_FROM 		= ", ";
		const JOIN_INNER 		= " INNER JOIN";
		const JOIN_CROSS 		= " CROSS JOIN";
		const JOIN_LEFT 		= " LEFT JOIN";
		const JOIN_LEFT_OUTER 	= " LEFT OUTER JOIN";
		const JOIN_RIGHT 		= " RIGHT JOIN";
		const JOIN_RIGHT_OUTER 	= " RIGHT OUTER JOIN";
		
		/**
		 * @var string|Select_Query
		 */
		private $tojoin;
		
		/**
		 * @var string
		 */
		private $type;
		
		/**
		 * @var string
		 */
		private $condition;
		
		/**
		 * @var string
		 */
		private $joinAs;
		
		/**
		 * @param Select_Query|string $tojoin
		 * @param string $type
		 * @param string $condition
		 * @param string $as
		 */
		public function __construct( $tojoin, $type=self::JOIN, $condition=false, $as=false ){
		
			$this->tojoin = $tojoin;
			$this->type = $type;
			$this->condition = $condition;
			$this->joinAs = $as;
		
		}
		
		/**
		 * @return boolean
		 */
		private function hasJoinAs()	{ return $this->joinAs !== false; 		}
		
		/**
		 * @return boolean
		 */
		private function hasCondition()	{ return $this->condition !== false;	}
		
		/**
		 * @return string
		 */
		private function getToJoin() 	{
			
			if( $this->tojoin instanceof Select_Query )	return " (" . $this->tojoin->get(false) . ")";
			else										return " " . $this->tojoin;
		
		}
		
		/**
		 * @return string
		 */
		private function getType()		{ return $this->type; 						}
		
		/**
		 * @return string
		 */
		private function getJoinAs() 	{ return " AS " . $this->joinAs; 			}
		
		/**
		 * @return string
		 */
		private function getCondition()	{ return " ON (" . $this->condition . ")"; 	}
		
		/** 
		 * (non-PHPdoc)
		 * @see iQuery::get()
		 */
		public function get(){
		
			$query = $this->getType() . $this->getToJoin();
			
			if( $this->hasJoinAs() )	$query .= $this->getJoinAs();
			if( $this->hasCondition() )	$query .= $this->getCondition();
			
			return $query;
		
		}

}

class Union_Query implements iQuery, OrderBy_Query {
	
	const UNION = " UNION ";
	
	/**
	 * @var array
	 */
	private $orderby;
	
	/**
	 * @var Select_Query
	 */
	private $firstQuery;

	/**
	 * @var Select_Query
	 */
	private $secondQuery;
	
	/**
	 * @param Select_Query $firstQuery
	 * @param Select_Query $secondQuery
	 */
	public function __construct( $firstQuery, $secondQuery ){
	
		$this->orderby= array();		
		
		$this->firstQuery = $firstQuery;
		$this->secondQuery = $secondQuery;
		
		return $this;
		
	}
	
	/**
	 * (non-PHPdoc)
	 * @see OrderBy_Query::orderBy()
	 */
	public function orderBy( $column, $order=self::ORDER_DEFAULT ){
			
		if( $order === self::ORDER_DESC )		$column .= " DESC";
		elseif( $order === self::ORDER_ASC )	$column .= " ASC";
		
		$this->orderby[] = $column;		
		
		return $this;

	}
	
	/**
	 * @return boolean
	 */
	private function hasOrderBy() { return count( $this->orderby ) > 0;	}

	/**
	 * @return string
	 */
	private function getOrderBy() { return " ORDER BY " . implode( ',', $this->orderby ); 	}
	
	/** 
	 * (non-PHPdoc)
	 * @see iQuery::get()
	 */
	public function get(){
	
		$return = "(" . $this->firstQuery->get(false) . ")" . self::UNION . "(" . $this->secondQuery->get(false) . ")";
						
		if( $this->hasOrderBy() ) $return .= $this->getOrderBy();
		
		return  $return . ";";
	
	}
    
}

abstract class Base_Query implements iQuery {

	/* Limit types */
	const LIMIT_MYSQL = 0;
	const LIMIT_POSTGRESQL = 1;

	/* Where */
	const WHERE_AND = " AND ";
	const WHERE_OR = " OR ";

	const END = " ;";
	const NO_END = "";

	/**
	 * @var string
	 */
	private $table;
	
	/**
	 * @var string
	 */
	private $tableAs;
	
	/**
	 * @var string
	 */
	private $joins;
	
	/**
	 * @var integer
	 */
	private $limit;

	/**
	 * @param string $table
	 * @param string $as
	 */
	public function __construct( $table, $as=false ){

		$this->joins 	= array();
		$this->table 	= $table;
		$this->tableAs 	= $as;		
		
		return $this;	
		
	}

	/**
	 * @param integer $limit
	 * @param string $offset
	 * @param integer $notation
	 */
	public function limit( $limit, $offset=false, $notation=self::LIMIT_MYSQL ){

		if( !$this->hasLimit() ) $this->limit = "";
			
		if( $offset !== false && $notation === self::LIMIT_MYSQL )		$this->limit .= " $offset,";

		$this->limit .= " $limit";

		if( $offset !== false && $notation === self::LIMIT_POSTGRESQL ) $this->limit .= " OFFSET $offset";		
		
		return $this;

	}

	/**
	 * @param Select_Query|string $tojoin
	 * @param string $type
	 * @param string $condition
	 * @param string $as
	 * @throws Exception
	 */
	public function join( $tojoin, $type=Join_Query::Join, $condition=false, $as=false ) {

		if( $tojoin instanceof Select_Query || is_string( $tojoin ) )
			$this->joins[] = new Join_Query( $tojoin, $type, $condition, $as );
		else
			throw new Exception( "Base_Query:: It's only possible to join a Select_Query instance or a string" );
		
		return $this;

	}

	/**
	 * @return boolean
	 */
	protected function hasAs()		{ return $this->tableAs !== false; 	}
	
	/**
	 * @return boolean
	 */
	protected function hasLimit()	{ return !is_null( $this->limit ); 	}
	
	/**
	 * @return boolean
	 */
	protected function hasJoins()	{ return count( $this->joins ) > 0; }

	/**
	 * @return string
	 */
	protected function getTable()	{ return " " . $this->table; 		}
	
	/**
	 * @return string
	 */
	protected function getAs()		{ return " AS " . $this->tableAs; 	}
	
	/**
	 * @return string
	 */
	protected function getLimit()	{ return " LIMIT" . $this->limit; 	}
	
	/**
	 * @return string
	 */
	protected function getJoins(){
		
		$query = "";
		
		foreach( $this->joins as $join ){
		
			$query .= $join->get();
		
		}
		
		return $query;
		
	}
	
	protected function getEnd($end){
		
		return ( $end ? self::END : self::NO_END );
		
	}
	
	/** 
	 * (non-PHPdoc)
	 * @see iQuery::get()
	 */
	public abstract function get($end=true);

}

abstract class Where_Query extends Base_Query {
	
	/* Where */
	const WHERE_AND = " AND ";
	const WHERE_OR = " OR ";
	
	/**
	 * @var array
	 */
	private $index;
	
	/**
	 * @var string
	 */
	private $where;
	
	/**
	 * @param string $table
	 * @param string $as
	 */
	public function __construct( $table, $as=false ){

		parent::__construct( $table, $as );
		
		$this->index = array();	
		
		return $this;
	
	}
	
	/**
	 * @param string|array $index
	 */
	public function forceIndex( $index ){
	
		if( is_array( $index ) )	array_merge( $this->index, $index );
		else						$this->index[] = $index;		
		
		return $this;
	
	}	
	
	/**
	 * @param string $where
	 * @param string $seperator
	 */
	public function where( $where, $seperator=false ){

		if( $this->hasWhere() && $seperator !== false ){ //Only use seperator when there already is a where statement

			if( $seperator === self::WHERE_AND || $seperator === self::WHERE_OR )
				$this->where .= $seperator;

		}

		$this->where .= $where;		
		
		return $this;

	}

	/**
	 * @return boolean
	 */
	protected function hasIndex()	{ return count( $this->index ) > 0; }
	
	/**
	 * @return boolean
	 */
	protected function hasWhere()	{ return !is_null( $this->where ); 	}
	
	/**
	 * @return string
	 */
	protected function getIndex()	{ return " FORCE INDEX(" . implode( ',', $this->index ) . ")"; 	}
	
	/**
	 * @return string
	 */
	protected function getWhere()	{ return  " WHERE " . $this->where;								}
	
}

class Select_Query extends Where_Query implements OrderBy_Query {

	/**
	 * @var array
	 */
	private $select;
	
	/**
	 * @var array
	 */
	private $groupBy;
	
	/**
	 * @var array
	 */
	private $having;
	
	/**
	 * @var array
	 */
	private $orderby;
	
	/**
	 * @param string $table
	 * @param string $as
	 */
	public function __construct( $table, $as=false ){
	
		parent::__construct( $table, $as );
	
		$this->select = array();
		$this->orderby= array();		
		
		return $this;
		
	}
	
	/**
	 * @param string $select
	 * @param string $selectAs
	 */
	public function select( $select, $selectAs=false ){
		
		if( $selectAs !== false ) $select .= ' AS ' . $selectAs;

		$this->select[] = $select;		
		
		return $this;
		
	}
	
	/**
	 * @param string $column
	 */
	public function groupBy( $column ){
		
		$this->groupBy[] = $column;		
		
		return $this;
		
	}
	
	/**
	 * @param string $statement
	 */
	public function having( $statement ){
		
		$this->having[] = $statement;		
		
		return $this;
		
	}
	
     /**
	 * (non-PHPdoc)
	 * @see OrderBy_Query::orderBy()
	 */
	public function orderBy( $column, $order=self::ORDER_DEFAULT ){
			
		if( $order === self::ORDER_DESC )		$column .= " DESC";
		elseif( $order === self::ORDER_ASC )	$column .= " ASC";
		
		$this->orderby[] = $column;		
		
		return $this;

	}
	
	/**
	 * @return boolean
	 */
	private function hasSelect()	{ return count( $this->select ) > 0; 	}

	/**
	 * @return boolean
	 */
	protected function hasGroupBy()	{ return count( $this->groupBy ) > 0; 	}

	/**
	 * @return boolean
	 */
	protected function hasHaving()	{ return count( $this->having ) > 0; 	}
	
	/**
	 * @return boolean
	 */
	private function hasOrderBy()	{ return count( $this->orderby ) > 0;	}
	
	/**
	 * @return string
	 */
	private function getSelect()	{ return "SELECT " . implode( ',', $this->select );			}

	/**
	 * @return string
	 */
	protected function getGroupBy()	{ return  " GROUP BY " . implode( ',', $this->groupBy );	}

	/**
	 * @return string
	 */
	protected function getHaving()	{ return  " HAVING " . implode( ' AND', $this->having );	}
	
	/**
	 * @return string
	 */
	private function getOrderBy() 	{ return " ORDER BY " . implode( ',', $this->orderby ); 	}
	
	/**
	 * @return string
	 */
	private function getFrom()		{ return " FROM" . $this->getTable();						}
	
	/** 
	 * (non-PHPdoc)
	 * @see iQuery::get()
	 */
	public function get($end=true){
	
		$return = $this->getSelect() . $this->getFrom();
						
		if( $this->hasAs() ) 		$return .= $this->getAs();
		if( $this->hasJoins() )		$return .= $this->getJoins();		
		if( $this->hasIndex() ) 	$return .= $this->getIndex();
		if( $this->hasWhere() ) 	$return .= $this->getWhere();		
		if( $this->hasGroupBy() ) 	$return .= $this->getGroupBy();
		if( $this->hasHaving() ) 	$return .= $this->getHaving();
		if( $this->hasOrderBy() ) 	$return .= $this->getOrderBy();
		if( $this->hasLimit() ) 	$return .= $this->getLimit();
		
		return  $return . $this->getEnd($end);
	
	}

}

class Insert_Query extends Base_Query {
	
	const VALUES_COLUMNS = 0;
	const VALUES_ONLY = 1;

	/**
	 * @var integer
	 */
	private $_type;
	
	/**
	 * @var array
	 */
	private $_columns = array();
	
	/**
	 * @var array
	 */
	private $_values = array();

	/**
	 * On first insert set Insert_Query type
	 * Force a user to use only the values in his query or a value-column combination
	 * 
	 * @param string $useColumn
	 * @throws Exception
	 */
	private function setType( $useColumn ){
		
		if( !$this->_type ){
			 $this->_type = ( $useColumn === false ? self::VALUES_ONLY : self::VALUES_COLUMNS );
		}else{
			if( ( $this->_type === self::VALUES_COLUMNS && $useColumn === false )
			&&	( $this->_type === self::VALUES_ONLY && $useColumn !== false ) )
			throw new Exception('Insert_Query:: Please only use values or a value-column combination for all your inserts');
		}
		
	}
	
	/**
	 * @param multitype $value
	 * @param string $column
	 */
	public function insert( $value, $column=false ){
		
		$this->setType( $column );
		
		if( $this->_type === self::VALUES_COLUMNS )	$this->_columns[] = $column;
		
		$this->_values[] = $value;		
		
		return $this;
					
	}

	/**
	 * @return boolean
	 */
	private function hasData()	{ return count( $this->_values ) > 0; }
	
	/**
	 * @throws Exception
	 * @return string
	 */
	private function getData()	{

		$data = false;
		
		if( $this->_type === self::VALUES_COLUMNS ){
			
			if( count( $this->_columns ) === count( $this->_values ) ){
				
				$data = " (" . implode( ',', $this->_columns ) . ") VALUES (" . implode( ',', $this->_values ) . ")";
				
			}else{
				
				throw new Exception('Insert_Query:: Your columns and values must match');
				
			}
			
		}else{
			
			$data = " VALUES (" . implode( ',', $this->_values ) . ")";
			
		}
		
		return ( $data !== false ? $data : " () VALUES ()" ); 
		 	
	}
	
	/**
	 * @return string
	 */
	private function getInsert(){ return "INSERT INTO ";				}
	
	/** 
	 * (non-PHPdoc)
	 * @see iQuery::get()
	 */
	public function get($end=true){

		return  $this->getInsert() . $this->getTable() . $this->getData() . $this->getEnd($end);

	}

}

class Update_Query extends Where_Query {

	/**
	 * @var array
	 */
	private $_columns = array();
	
	/**
	 * @var array
	 */
	private $_values = array();

	/**
	 * @param string $column
	 * @param multitype $value
	 */
	public function update( $value, $column ){

		$this->_columns[] 	= $column;
		$this->_values[] 	= $value;		
		
		return $this;
			
	}

	/**
	 * @return boolean
	 */
	private function hasData()	{ return count( $this->$_values ) > 0; 	}
	
	/**
	 * @return string
	 */
	private function getData()	{

		$data = false;

		if( count( $this->_columns ) === count( $this->_values ) ){
				
			$data = implode( ',', array_map( function($c,$v){ return "$c=$v"; }, $this->_columns, $this->_values ) );
			
		}else{
				
			throw new Exception('Update_Query:: Your columns and values must match');
			
		}

		return ( $data !== false ? $data : "" );

	}

	/**
	 * @return string
	 */
	private function getUpdate(){ return "UPDATE ";	}

	/** 
	 * (non-PHPdoc)
	 * @see iQuery::get()
	 */
	public function get($end=true){

		$return = $this->getUpdate() . $this->getTable();
								
		if( $this->hasAs() ) 		$return .= $this->getAs();
		
		$return .= " SET " . $this->getData();
		
		if( $this->hasIndex() ) 	$return .= $this->getIndex();
		if( $this->hasWhere() ) 	$return .= $this->getWhere();		
		if( $this->hasLimit() ) 	$return .= $this->getLimit();
		
		return  $return . $this->getEnd($end);

	}

}

class Delete_Query extends Where_Query {

	/**
	 * @return string
	 */
	private function getDelete()	{ return "DELETE";						}
	
	/**
	 * @return string
	 */
	private function getFrom()		{ return " FROM" . $this->getTable();	}

	/**
	 * (non-PHPdoc)
	 * @see iQuery::get()
	 */
	public function get($end=true){

		$return = $this->getDelete() . $this->getFrom();
			
		if( $this->hasAs() ) 		$return .= $this->getAs();
		if( $this->hasWhere() ) 	$return .= $this->getWhere();
		if( $this->hasLimit() ) 	$return .= $this->getLimit();
			
		return  $return . $this->getEnd($end);

	}

}
