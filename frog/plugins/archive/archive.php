<?php

/**
 * Frog CMS - Content Management Simplified. <http://www.madebyfrog.com>
 * Copyright (C) 2008 Philippe Archambault <philippe.archambault@gmail.com>
 *
 * This file is part of Frog CMS.
 *
 * Frog CMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Frog CMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Frog CMS.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Frog CMS has made an exception to the GNU General Public License for plugins.
 * See exception.txt for details and the full text.
 */

/**
 * The Archive plugin provides an Archive pagetype behaving similar to a blog or news archive.
 *
 * @package frog
 * @subpackage plugin.archive
 *
 * @author Philippe Archambault <philippe.archambault@gmail.com>
 * @version 1.0
 * @since Frog version 0.9.0
 * @license http://www.gnu.org/licenses/gpl.html GPL License
 * @copyright Philippe Archambault, 2008
 */

/**
 * The Archive class...
 */
class Archive
{
    public function __construct(&$page, $params)
    {
        $this->page =& $page;
	
	$this->route = $this->route(array(
		'/tag/(.+)/' => 'tag',
		'/([0-9]{4})/([0-9]{2})/([0-9]{2})/' => 'day',
		'/([0-9]{4})/([0-9]{2})/' => 'month',
		'/([0-9]{4})/' => 'year',
		'/[0-9]{4}/[0-9]{2}/[0-9]{2}/(.+)/(print)|^$' => 'page',
		'/(.+)/(print)|^$' => 'page',
		'/' => 'all'
	), $params, $regs);
	$this->args = $regs;
	
	if($this->route === false){
		page_not_found();
		return;
	}
	if($this->route === 'tag'){
		$this->tag = urldecode($regs[0]);
	}
	
	if($this->route === 'page'){
		$this->print = (bool)$regs[1];
		$this->_displayPage($regs[0]);
		return;
	} elseif ($this->route !== 'all'){
		$this->_archiveBy($this->route, $params);
	}
    }
    
    protected function route($rules, $params, &$regs){
	foreach($rules as $rule => $target){
		$retRegs = array();
		$frags = explode('/', $rule);
		if($rule[0] === '/'){
			array_shift($frags);
		}
		$last = count($frags) - 1;
		
		foreach($frags as $idx => $frag){
			// check rule fragments all have content (except last)
			if($idx !== $last && strlen($frag) === 0){
				break;
			}
			
			if(strlen($frag) === 0){
				//if on the last rule fragment... is it a trailing slash? or not
				if(isset($params[$idx]) && strlen($params[$idx]) !== 0){
					//expected end of parameters but more params found...
					break;
				}
			} else {
				// check regex
				$match = ereg($frag,$params[$idx],$r);
				if(!$match){
					break;
				}
				
				array_shift($r);
				$retRegs = array_merge($retRegs, $r);
			}
			
			if($idx === $last){
				//found a match
				$regs = $retRegs;
				return $target;
			}
		}
	}
	return false;
    }
    
    private function _archiveBy($interval, $params)
    {
        $this->interval = $interval;
        
        global $__FROG_CONN__;
        
        $page = $this->page->children(array(
            'where' => "behavior_id = 'archive_{$interval}_index'",
            'limit' => 1
        ), array(), true);
        
        if ($page) {
            $this->page = $page;
            $month = isset($params[1]) ? (int)$params[1]: 1;
            $day = isset($params[2]) ? (int)$params[2]: 1;

            $this->page->time = mktime(0, 0, 0, $month, $day, (int)$params[0]);
        } else {
            page_not_found();
        }
    }
    
    private function _displayPage($slug)
    {
        if ( ! $this->page = find_page_by_slug($slug, $this->page))
            page_not_found();
    }
    
    function formatTitle($title){
	if(in_array($this->route, array('all','page','tag'))){
		return $title;
	}
	return strftime($title,strtotime(join('-',$this->args)));
    }
    
    function get()
    {
	if($this->route === 'all'){
		return $this->page->children(array('order' => 'page.created_on DESC'));
	} elseif ($this->route === 'page'){
		return $this->page;
	} elseif ($this->route === 'tag'){
		$tag = urldecode($this->args[0]);
		return $this->archivesByTagName($tag);
	} else {
		$date = join('-', $this->args);
		$pages = $this->page->parent->children(array(
		    'where' => "page.created_on LIKE '{$date}%'",
		    'order' => 'page.created_on DESC'
		));
		return $pages;
	}
    }
    
    protected function _fetchSQL($sql){
	global $__FROG_CONN__;
        
        $out = array();
        
        $stmt = $__FROG_CONN__->prepare($sql);
        $stmt->execute(array($this->page->id));
        
        while ($date = $stmt->fetchColumn())
            $out[] = $date;
        
        return $out;
    }
    
    function archivesByYear()
    {
        $sql = "SELECT DISTINCT(DATE_FORMAT(created_on, '%Y')) FROM ".TABLE_PREFIX."page WHERE parent_id=? AND status_id != ".Page::STATUS_HIDDEN." ORDER BY created_on DESC";
        return $this->_fetchSQL($sql);
    }
    
    function archivesByMonth($year='all')
    {
        $sql = "SELECT DISTINCT(DATE_FORMAT(created_on, '%Y/%m')) FROM ".TABLE_PREFIX."page WHERE parent_id=? AND status_id != ".Page::STATUS_HIDDEN." ORDER BY created_on DESC";
        return $this->_fetchSQL($sql);
    }
    
    function archivesByDay($year='all')
    {
        if ($year == 'all') {
		$year = '';
	}
        $sql = "SELECT DISTINCT(DATE_FORMAT(created_on, '%Y/%m/%d')) FROM ".TABLE_PREFIX."page WHERE parent_id=? AND status_id != ".Page::STATUS_HIDDEN." ORDER BY created_on DESC";
        return $this->_fetchSQL($sql);
    }
    
    function archivesByTagName($tag){
	//get tag id
	$sql = "SELECT id FROM ".TABLE_PREFIX."tag WHERE name = '$tag'";
	$id = array_shift($this->_fetchSQL($sql));
	if($id === null){
		return array();
	}
	//get page ids
	$sql = "SELECT page_id FROM ".TABLE_PREFIX."page_tag WHERE tag_id = {$id}";
	$pageids = $this->_fetchSQL($sql);
	
	if(empty($pageids)){
		return array();
	}
	
	$pageidstr = implode(',',$pageids);
	$pages = $this->page->parent->children(array(
	    'where' => "page.id IN ({$pageidstr})",
	    'order' => 'page.created_on DESC'
	));
	return $pages;
    }
    
    function getTags($mod = array()) {
	global $__FROG_CONN__;
    
	$whereOrder = array_merge(array(
		'where' => 'count != 0',
		'order' => 'name DESC'
	),$mod);
	
	$output = array();
	
	$sql = 'SELECT id,name,count FROM '.TABLE_PREFIX.'tag '.(strlen($whereOrder['where']) > 0 ? 'WHERE '.$whereOrder['where'].' ' : '').'ORDER BY '.$whereOrder['order'];
	$stmt = $__FROG_CONN__->prepare($sql);
	$stmt->execute();
    
	while ($obj = $stmt->fetchObject()){
		$output[] = array('name'=>$obj->name, 'count'=>$obj->count, 'id'=>$obj->id);
	}
	
	return $output;
    }
    
}

class PageArchive extends Page
{
    protected function setUrl()
    {
        $this->url = trim($this->parent->url . date('/Y/m/d/', strtotime($this->created_on)). $this->slug, '/');
    }
    
    public function title() { return isset($this->time) ? strftime($this->title, $this->time): $this->title; }
    
    public function breadcrumb() { return isset($this->time) ? strftime($this->breadcrumb, $this->time): $this->breadcrumb; }
    
    public function raw_content($part='body', $inherit=false)
    {
        // if part exist we generate the content en execute it!
	$pts = PagePart::findByPageId($this->id);
	$p;
	foreach($pts as $pt){
		if($pt->name === $part){
			$p = $pt;
			break;
		}
	}
        if (isset($p))
        {
            ob_start();
            eval('?>'.$p->content);
            $out = ob_get_contents();
            ob_end_clean();
            return $out;
        }
        else if ($inherit && $this->parent)
        {
            return $this->parent->content($part, true);
        }
    }
}
