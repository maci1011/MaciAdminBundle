<?php

namespace Maci\AdminBundle;

class MaciPager
{
	private $result;

	private $limit;

	private $page;

	private $range;

	private $length;

	private $form;

	private $identifiers;

	public function __construct($result = null, $page = 1, $limit = 10, $range = 5)
	{
		$this->result = $result;
		$this->limit = $limit;
		$this->range = $range;
		$this->setPage($page);
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
		$this->page = ($this->getMaxPages() < $page) ? 1 : $page;
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

	public function getForm()
	{
		return $this->form;
	}

	public function setForm($form)
	{
		$this->form = $form;
	}

	public function getIdentifiers()
	{
		return $this->identifiers;
	}

	public function setIdentifiers($identifiers)
	{
		$this->identifiers = $identifiers;
	}

	public function getIdentifier($id)
	{
		return $this->identifiers[$id];
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
		return ($this->getPage() - 1) * $this->limit;
	}

	public function requiresPagination()
	{
		return ( $this->limit && $this->getLength() > $this->limit );
	}

	public function hasPrev()
	{
		return ( $this->getPage() > 1 );
	}

	public function hasNext()
	{
		return ( $this->getMaxPages() > $this->getPage() );
	}

	public function getNext()
	{
		return $this->getPage() + 1;
	}

	public function getPrev()
	{
		return $this->getPage() - 1;	
	}

	public function getPageRange()
	{
		$return = array();

		$min = max(1, $this->getPage() - $this->range);
		$max = min($this->getMaxPages(), $this->getPage() + $this->range);

		for ($i = $min; $i <= $max; $i++) {
			$return []= $i;
		}

		return $return;
	}

	public function hasRangeFirst()
	{
		return in_array( 1 , $this->getPageRange() );
	}

	public function hasRangeLast()
	{
		return in_array( $this->getMaxPages() , $this->getPageRange() );
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

	public function isCurrent($candidate)
	{
		return $candidate == $this->getPage();
	}
}
