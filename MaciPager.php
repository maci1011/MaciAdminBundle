<?php

namespace Maci\AdminBundle;

class MaciPager
{
	protected $result;

	protected $limit;

	protected $page;

	protected $range;

	protected $length;

	protected $fields_list;

	protected $filters_form;

	public function __construct($result = null, $limit = 10, $page = 1, $range = 5)
	{
		$this->result = $result;
		$this->limit = $limit;
		$this->page = $page;
		$this->range = $range;
		$this->fields_list = array();
		$this->filters_form = false;
	}

	public function getResult()
	{
		return $this->result;
	}

	public function setResult($result)
	{
		$this->result = $result;
	}

	public function getPage()
	{
		return $this->page;
	}

	public function setPage($page)
	{
		$this->page = $page;
	}

	public function getLimit()
	{
		return $this->limit;
	}

	public function setLimit($limit)
	{
		$this->limit = $limit;
	}

	public function getRange()
	{
		return $this->range;
	}

	public function setRange($range)
	{
		$this->range = $range;
	}

	public function getListFields()
	{
		return $this->fields_list;
	}

	public function setListFields($fields_list)
	{
		$this->fields_list = $fields_list;
	}

	/* Utils */

	public function getLength()
	{
		return count( $this->result );
	}

	public function getMaxPages()
	{
		return ( 0 < $this->limit ? ceil( $this->getLength() / $this->limit ) : 1 );
	}

	public function getOffset()
	{
		return ($this->page - 1) * $this->limit;
	}

	public function requiresPagination()
	{
		return ( $this->limit && $this->getLength() > $this->limit );
	}

	public function hasPrev()
	{
		return ( $this->page > 1 );
	}

	public function hasNext()
	{
		return ( $this->getMaxPages() > $this->page );
	}

	public function getNext()
	{
		return $this->page + 1;
	}

	public function getPrev()
	{
		return $this->page - 1;	
	}

	public function getPageRange()
	{
		$return = array();

		$min = max(1, $this->page - $this->range);
		$max = min($this->getMaxPages(), $this->page + $this->range);

		for ($i = $min; $i <= $max; $i++) {
			$return []= $i;
		}

		return $return;
	}

	public function getPageList()
	{
		$return = array();

		if ( 0 < $this->limit ) {
			$from = $this->getOffset();
			$to = $from + $this->limit;
		} else {
			$from = 0;
			$to = count($this->result);
		}

		$i = 0;
		foreach ($this->result as $key => $value) {
			if ( $from <= $i && $i < $to) {
				$return[$key] = $value;
			}
			$i++;
		}

		return $return;
	}

	public function current($candidate)
	{
		return $candidate == $this->page;
	}
}
