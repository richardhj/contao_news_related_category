<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Leo Feyer 2005-2012
 * @author     Leo Feyer <http://www.contao.org>
 * @package    News
 * @license    LGPL
 * @filesource
 */


/**
 * Class ModuleNewsListRelatedCategory
 * * based on ModuleNewsList
 *
 * Front end module "news list related category".
 * @copyright  Richard Henkenjohann 2013
 * @author     Richard Henkenjohann
 * @package    Controller
 */
class ModuleNewsListRelatedCategory extends ModuleNewsList
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_newslist';


	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		return parent::generate();
	}


	/**
	 * Generate the module
	 */
	protected function compile()
	{
		$time = time();
		$skipFirst = intval($this->skipFirst);
		$offset = 0;
		$limit = null;
		$this->Template->articles = array();

		// Maximum number of items
		if ($this->news_numberOfItems > 0)
		{
			$limit = $this->news_numberOfItems;
		}

		// Get the total number of items
		$objTotal = $this->Database->execute("SELECT COUNT(*) AS total FROM tl_news WHERE pid IN(" . implode(',', array_map('intval', $this->news_archives)) . ")" . (($this->news_featured == 'featured') ? " AND featured=1" : (($this->news_featured == 'unfeatured') ? " AND featured=''" : "")) . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<$time) AND (stop='' OR stop>$time) AND published=1" : ""));
		$total = $objTotal->total - $skipFirst;

		// Split the results
		if ($this->perPage > 0 && (!isset($limit) || $this->news_numberOfItems > $this->perPage))
		{
			// Adjust the overall limit
			if (isset($limit))
			{
				$total = min($limit, $total);
			}

			// Get the current page
			$page = $this->Input->get('page') ? $this->Input->get('page') : 1;

			// Do not index or cache the page if the page number is outside the range
			if ($page < 1 || $page > max(ceil($total/$this->perPage), 1))
			{
				global $objPage;
				$objPage->noSearch = 1;
				$objPage->cache = 0;

				// Send a 404 header
				header('HTTP/1.1 404 Not Found');
				return;
			}

			// Set limit and offset
			$limit = $this->perPage;
			$offset = (max($page, 1) - 1) * $this->perPage;

			// Overall limit
			if ($offset + $limit > $total)
			{
				$limit = $total - $offset;
			}

			// Add the pagination menu
			$objPagination = new Pagination($total, $this->perPage);
			$this->Template->pagination = $objPagination->generate("\n  ");
		}

		// Get archive id
		$objCurrentArticle = $this->Database->prepare("SELECT pid FROM tl_news WHERE id=? OR alias=?")
		                             ->limit(1)
		                             ->execute((is_numeric($this->Input->get('items')) ? $this->Input->get('items') : 0), $this->Input->get('items'));

		$objArticlesStmt = $this->Database->prepare("SELECT *, author AS authorId, (SELECT title FROM tl_news_archive WHERE tl_news_archive.id=tl_news.pid) AS archive, (SELECT jumpTo FROM tl_news_archive WHERE tl_news_archive.id=tl_news.pid) AS parentJumpTo, (SELECT name FROM tl_user WHERE id=author) AS author FROM tl_news WHERE pid=?" . (($this->news_featured == 'featured') ? " AND featured=1" : (($this->news_featured == 'unfeatured') ? " AND featured=''" : "")) . (!BE_USER_LOGGED_IN ? " AND (start='' OR start<$time) AND (stop='' OR stop>$time) AND published=1" : "") . " ORDER BY date DESC");

		// Limit the result
		if (isset($limit))
		{
			$objArticlesStmt->limit($limit, $offset + $skipFirst);
		}
		elseif ($skipFirst > 0)
		{
			$objArticlesStmt->limit(max($total, 1), $skipFirst);
		}

		$objArticles = $objArticlesStmt->execute($objCurrentArticle->pid);

		// No items found
		if ($objCurrentArticle->numRows < 1 || $objArticles->numRows < 1)
		{
			$this->Template = new FrontendTemplate('mod_newsarchive_empty');
		}

		$this->Template->archives = $this->news_archives;
		$this->Template->articles = $this->parseArticles($objArticles);
		$this->Template->empty = $GLOBALS['TL_LANG']['MSC']['emptyList'];
	}
}
