<?php

namespace Maci\AdminBundle;

class MaciPager
{
	protected $list;

	protected $limit;

	protected $page;

	protected $range;

	protected $length;

	protected $list_fields;

	protected $filters_form;

	public function __construct($list = array(), $limit = 10, $page = 1, $range = 5)
	{
		$this->list = $list;
		$this->length = count( $list );
		$this->limit = $limit;
		$this->page = $page;
		$this->range = $range;
		$this->list_fields = array();
		$this->filters_form = false;
	}

	public function getList()
	{
		return $this->list;
	}

	public function setList($list)
	{
		$this->list = $list;
		$this->length = count( $list );
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
		return $this->list_fields;
	}

	public function setListFields($list_fields)
	{
		$this->list_fields = $list_fields;
	}

	public function getFiltersForm()
	{
		return $this->filters_form;
	}

	public function setFiltersForm($filters_form)
	{
		$this->filters_form = $filters_form;
	}

	/* Utils */

	public function getLength()
	{
		return $this->length;
	}

	public function getMaxPages()
	{
		return ( 0 < $this->limit ? ceil( $this->length / $this->limit ) : 1 );
	}

	public function getOffset()
	{
		return ($this->page - 1) * $this->limit;
	}

	public function requiresPagination()
	{
		return ( $this->limit && $this->length > $this->limit );
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
			$to = count($this->list);
		}

		$i = 0;
		foreach ($this->list as $key => $value) {
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
