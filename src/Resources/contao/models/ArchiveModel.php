<?php
/*
 * Posts'n'pages extension for Contao Open Source CMS.
 *
 * @copyright  Arne Stappen (alias aGoat) 2017
 * @package    contao-postsnpages
 * @author     Arne Stappen <mehh@agoat.xyz>
 * @link       https://agoat.xyz
 * @license    LGPL-3.0
 */

namespace Contao;


/**
 * Reads and writes archives
 */
class ArchiveModel extends \Model 
{

	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_archive';

	
	/**
	 * Find all published archives by their id(s)
	 *
	 * @param integer|array $varIds     The archive id(s)
	 * @param array   $arrOptions An optional options array
	 *
	 * @return Model\Collection|ArchiveModel|null A collection of models or null if there are no archives
	 */
	public static function findByIds($varIds, array $arrOptions=array())
	{
		$t = static::$strTable;
		
		if (is_array($varIds))
		{
			$arrColumns = array("$t.id in ('" . implode("','", $varIds) . "')");
			$arrValues = array();
		}
		else
		{
			$arrColumns = array("$t.id=?");
			$arrValues = array($varIds);
		}

		return static::findBy($arrColumns, $arrValues, $arrOptions);
	}
}
