<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
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
 * @copyright  InfinitySoft 2010
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    DomainLink
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * Class DomainLink
 *
 * Finden und erstellen von Domainübergreifenden Links.
 * @copyright  InfinitySoft 2010
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    DomainLink
 */
class DomainLink extends Controller
{
	/**
	 * DNS page related cache.
	 * @var array
	 */
	protected static $arrDNSCache = array();
	
	
	/**
	 * Initialize the object
	 */
	public function __construct() {
		parent::__construct();
		$this->import('Database');
	}
	
	
	/**
	 * Search recursive the page dns.
	 * @param array
	 * @return string
	 */
	public function findPageDNS($objPage) {
		if ($objPage != null) {
			// inherit page details
			if (is_array($objPage))
			{
				$objPage = $this->getPageDetails($objPage['id']);
			}
			
			// use cached dns
			if (isset(DomainLink::$arrDNSCache[$objPage->id]))
			{
				return DomainLink::$arrDNSCache[$objPage->id];
			}
			// the current page is the root page
			else if ($objPage->type == 'root')
			{
				if (!empty($objPage->dns))
				{
					return DomainLink::$arrDNSCache[$objPage->id] = $objPage->dns;
				}
			}
			// search for a root page with defined dns
			else
			{
				$arrTrail = $objPage->trail;
				$objRootPage = $this->Database->execute("
						SELECT
							*
						FROM
							`tl_page`
						WHERE
								`id` IN (" . implode(',', $arrTrail) . ")
							AND `type`='root'
							AND `dns`!=''
						ORDER BY
							`id`=" . implode(',`id`=', $arrTrail) . "
						LIMIT
							1");
				if ($objRootPage->next())
				{
					foreach ($arrTrail as $intId)
					{
						DomainLink::$arrDNSCache[$intId] = $objRootPage->dns;
					}
					return $objRootPage->dns;
				}
			}
		}
		
		// no page dns found, use base dns
		if (!empty($GLOBALS['TL_CONFIG']['baseDNS']))
		{
			return $GLOBALS['TL_CONFIG']['baseDNS'];
		}
		
		// no base dns defined, use request dns
		else
		{
			$xhost = $this->Environment->httpXForwardedHost;
			return (!empty($xhost) ? $xhost . '/' : '') . $this->Environment->httpHost;
		}
	}
	
	
	/**
	 * Replace insert tags with their values
	 * @param string
	 * @param bool
	 * @return string
	 */
	public function replaceDomainLinkInsertTags($strBuffer, $blnCache=false)
	{
		global $objPage;

		switch ($strBuffer)
		{
		case 'dns::domain':
			return $this->findPageDNS($objPage != null ? $objPage->row() : null);
		}

		return false;
	}

	
	/**
	 * Generate an absolute url if the domain of the target page is different from the domain of the current page.
	 * 
	 * @param array
	 * @param string
	 * @param string
	 * @return string
	 */
	public function generateDomainLink($arrRow, $strParams, $strUrl, $blnForce = false)
	{
		global $objPage;
		if (!preg_match('#^(\w+://)#', $strUrl) && !preg_match('#^\{\{[^\}]*_url[^\}]*\}\}$#', $strUrl))
		{
			// find the current page dns
			$strCurrent = $this->findPageDNS($objPage != null ? $objPage : null);
			// find the target page dns
			$strTarget = $this->findPageDNS($arrRow);
			// force absolute url
			$blnForce = $blnForce ? true : $strCurrent != $strTarget;
			// find the protocol
			switch ($GLOBALS['TL_CONFIG']['secureDNS'])
			{
			case 'insecure':
				$strProtocol = 'http';
				if ($this->Environment->ssl)
				{
					$blnForce = true;
				}
				break;

			case 'secure':
				$strProtocol = 'https';
				if (!$this->Environment->ssl)
				{
					$blnForce = true;
				}
				break;
				
			default:
			case 'auto':
				if ($this->Environment->ssl)
				{
					$strProtocol = 'https';
				}
				else
				{
					$strProtocol = 'http';
				}
				break;
			}
			if (strlen($strTarget) && $blnForce) {
				$strUrl = $strProtocol . '://' . $strTarget . ($strUrl[0] == '/' ? '' : $GLOBALS['TL_CONFIG']['websitePath'] . '/') . $strUrl;
			}
		}
		return $strUrl;
	}
	
}
?>