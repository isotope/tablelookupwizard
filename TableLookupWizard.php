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
 * @copyright  Isotope eCommerce Workgroup 2009-2012
 * @author     Fred Bliss <fred.bliss@intelligentspark.com>
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class TableLookupWizard extends Widget
{

	/**
	 * Submit user input
	 * @var boolean
	 */
	protected $blnSubmitInput = true;

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';

	/**
	 * Allowed row ids
	 * @var array
	 */
	protected $arrIds = false;
	
	/**
	 * SQL search operator
	 */
	protected $strOperator = ' OR ';


	/**
	 * Construct object and import database
	 * @param array
	 */
	public function __construct($arrAttributes=false)
	{
		parent::__construct($arrAttributes);

		$this->import('Database');
	}


	/**
	 * Store config for ajax upload.
	 *
	 * @access public
	 * @param string $strKey
	 * @param mixed $varValue
	 * @return void
	 */
	public function __set($strKey, $varValue)
	{
		switch ($strKey)
		{
			case 'allowedIds':
				$this->arrIds = deserialize($varValue);
				break;

			case 'searchFields':
				$arrFields = array();
				foreach( $varValue as $k => $v )
				{
					if (is_numeric($k))
					{
						$arrFields[] = $v;
					}
					else
					{
						$arrFields[] = $v . ' AS ' . $k;
					}
				}
				parent::__set($strKey, $arrFields);
				break;

			case 'foreignTable':
				$this->loadDataContainer($varValue);
				$this->loadLanguageFile($varValue);
				parent::__set($strKey, $varValue);
				break;
			
			case 'matchAllKeywords':
				$this->strOperator = $varValue ? ' AND ' : ' OR ';
				break;

			case 'mandatory':
				$this->arrConfiguration['mandatory'] = $varValue ? true : false;
				break;

			default:
				parent::__set($strKey, $varValue);
				break;
		}
	}


	/**
	 * Validate input and set value
	 */
	public function validator($varInput)
	{
		if ($this->mandatory && ((is_array($varInput) && !count($varInput)) || $varInput == ''))
		{
			$this->addError(sprintf($GLOBALS['TL_LANG']['ERR']['mandatory'], $this->strLabel));
		}

		return $varInput;
	}



	/**
	 * Generate the widget and return it as string
	 * @return string
	 */
	public function generate()
	{
		if ($this->Input->get('tableLookupWizard') == $this->strId)
		{
			while(ob_end_clean());

			if (version_compare(VERSION, '2.10', '<'))
			{
				echo $this->generateAjax();
			}
			else
			{
				echo json_encode(array
				(
					'content'	=> $this->generateAjax(),
					'token'		=> REQUEST_TOKEN,
				));
			}
			exit;
		}

		$GLOBALS['TL_CSS'][] = 'system/modules/tablelookupwizard/html/tablelookup.css';

		if (!$this->Input->get('noajax'))
			$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/tablelookupwizard/html/tablelookup.js';

		$this->loadLanguageFile($this->foreignTable);

		$arrIds = deserialize($this->varValue, true);

		if ($arrIds[0] == '')
		{
			$arrIds = array(0);
		}

		$strReset = '';
		if ($this->fieldType == 'radio')
		{
			$strReset = '
    <tr class="reset">
      <td><input type="radio" class="radio" name="' . $this->strId . '" id="reset_' . $this->strId . '" value=""' . ($arrIds[0]==0 ? ' checked="checked"' : '') . ' /></td>
      <td colspan="' . (count($this->listFields)). '"><label for="reset_' . $this->strId . '" class="tl_change_selected">' . $GLOBALS['TL_LANG']['MSC']['resetSelected'] . '</label></td>
    </tr>';
		}

		// User has javascript disabled and clicked on link
		if ($this->Input->get('noajax'))
		{
			$arrResults = $this->Database->execute("SELECT id, " . implode(', ', $this->listFields) . " FROM {$this->foreignTable}" . (strlen($this->sqlWhere) ? " WHERE {$this->sqlWhere}" : '') . " ORDER BY id=" . implode(' DESC, id=', $arrIds) . " DESC")->fetchAllAssoc();
			$strResults = $this->listResults($arrResults) . $strReset;
		}
		else
		{
			$arrResults = $this->Database->execute("SELECT id, " . implode(', ', $this->listFields) . " FROM {$this->foreignTable} WHERE id IN (" . implode(',', $arrIds) . ")" . (strlen($this->sqlWhere) ? " AND {$this->sqlWhere}" : ''))->fetchAllAssoc();
			$strResults = $this->listResults($arrResults);

			$strResults .= '
    <tr class="jserror">
      <td colspan="' . (count($this->listFields)+1) . '"><a href="' . $this->addToUrl('noajax=1') . '">' . $GLOBALS['TL_LANG']['MSC']['tlwJavascript'] . '</a></td>
    </tr>' . $strReset . '
    <tr class="search" style="display:none">
      <td colspan="' . (count($this->listFields)+1) . '"><label for="ctrl_' . $this->strId . '_search">' . ($this->searchLabel=='' ? $GLOBALS['TL_LANG']['MSC']['searchLabel'] : $this->searchLabel) . ':</label> <input type="text" id="ctrl_' . $this->strId . '_search" name="keywords" class="tl_text" autocomplete="off" /></td>
    </tr>';
		}


		$strBuffer = '
<table cellspacing="0" cellpadding="0" id="ctrl_' . $this->strId . '" class="tl_tablelookupwizard" summary="Table data">
  <thead>
    <tr>
      <th class="head_0 col_first">&nbsp;</th>';

      	$i = 1;
      	foreach( $this->listFields as $k => $v )
      	{
      		$field = is_numeric($k) ? $v : $k;

      		$strBuffer .= '
  	  <th class="head_' . $i . ($i==count($this->listFields) ? ' col_last' : '') . '">' . $this->formatLabel($this->foreignTable, $field) . '</th>';

      		$i++;
      	}

  	  	$strBuffer .= '
    </tr>
  </thead>
  <tbody>
' . $strResults . '
  </tbody>
</table>';

		if (!$this->Input->get('noajax'))
		{
			// inject JS in HTML5 style from Contao 2.10
			$strScriptBegin = (version_compare(VERSION, '2.9', '>') ? '<script>' : '<script type="text/javascript">
<!--//--><![CDATA[//><!--');
			$strScriptEnd = (version_compare(VERSION, '2.9', '>') ? '</script>' : '//--><!]]>
</script>');

			$strBuffer .= $strScriptBegin . '
window.addEvent(\'domready\', function() {
  new TableLookupWizard(\'' . $this->strId . '\');
});' . $strScriptEnd;
		}

		return $strBuffer;
	}


	public function generateAjax()
	{
		$arrKeywords = trimsplit(' ', $this->Input->get('keywords'));

		$strFilter = '';
		$arrProcedures = array();
		$arrValues = array();

		foreach( $arrKeywords as $keyword )
		{
			if (!strlen($keyword))
				continue;

			$arrProcedures[] .= '(' . implode(' LIKE ? OR ', $this->searchFields) . ' LIKE ?)';
			$arrValues = array_merge($arrValues, array_fill(0, count($this->searchFields), '%'.$keyword.'%'));
		}

		if (!count($arrProcedures))
			return '';

		$varData = $this->Input->get($this->strName);

		if ($this->fieldType == 'checkbox' && is_array($varData) && count($varData))
		{
			$strFilter = ") AND id NOT IN (" . implode(',', $varData);
		}
		elseif ($this->fieldType == 'radio' && $varData != '')
		{
			$strFilter = ") AND (id!='$varData'";
		}

		$arrResults = $this->Database->prepare("SELECT id, " . implode(', ', $this->listFields) . " FROM {$this->foreignTable} WHERE (" . implode($this->strOperator, $arrProcedures) . $strFilter . ")" . (strlen($this->sqlWhere) ? " AND {$this->sqlWhere}" : ''))
									  ->execute($arrValues)
									  ->fetchAllAssoc();

		$strBuffer = $this->listResults($arrResults, true);

		if (!strlen($strBuffer))
			return '<tr class="found empty"><td colspan="' . (count($this->listFields)+1) . '">' . sprintf($GLOBALS['TL_LANG']['MSC']['tlwNoResults'], $this->Input->get('keywords')) . '</td></tr>';

		return $strBuffer;
	}


	protected function listResults($arrResults, $blnAjax=false)
	{
		$c=0;
		$strResults = '';

		foreach( $arrResults as $row )
		{
			if (is_array($this->arrIds) && !in_array($row['id'], $this->arrIds))
				continue;

			switch( $this->fieldType )
			{
				case 'radio':
					$input = '<input type="radio" class="radio" name="' . $this->strId . '" value="' . $row['id'] . '"' . ($blnAjax ? '' : $this->optionChecked($row['id'], $this->varValue)) . ' />';
					break;

				case 'checkbox':
					$input = '<input type="checkbox" class="checkbox" name="' . $this->strId . '[]" value="' . $row['id'] . '"' . ($blnAjax ? '' : $this->optionChecked($row['id'], $this->varValue)) . ' />';
					break;

				default:
					$input = '';
					break;
			}

			$strResults .= '
    <tr class="' . ($c%2 ? 'even' : 'odd') . ($c==0 ? ' row_first' : '') . ($blnAjax ? ' found' : '') . '">
      <td class="col_0 col_first">'.$input.'</td>';

      		$i = 1;
      		foreach( $row as $field => $value )
      		{
      			if ($field == 'id' && !in_array('id', $this->listFields))
      				continue;

      			$strResults .= '
      <td class="col_' . $i . '">' . $this->formatValue($this->foreignTable, $field, $value) . '</td>';

      			$i++;
      		}

      		$strResults .= '
    </tr>';

    		$c++;
		}

		return $strResults;
	}


	/**
	 * Format value (based on DC_Table::show(), Contao 2.9.0)
	 * @param  mixed
	 * @param  string
	 * @param  string
	 * @return string
	 */
	protected function formatValue($table, $field, $value)
	{
		$value = deserialize($value);

		// Get field value
		if (strlen($GLOBALS['TL_DCA'][$table]['fields'][$field]['foreignKey']))
		{
			$temp = array();
			$chunks = explode('.', $GLOBALS['TL_DCA'][$table]['fields'][$field]['foreignKey']);

			$objKey = $this->Database->execute("SELECT " . $chunks[1] . " AS value FROM " . $chunks[0] . " WHERE id IN (" . implode(',', array_map('intval', (array)$value)) . ")");

			return implode(', ', $objKey->fetchEach('value'));
		}

		elseif (is_array($value))
		{
			foreach ($value as $kk=>$vv)
			{
				if (is_array($vv))
				{
					$vals = array_values($vv);
					$value[$kk] = $vals[0].' ('.$vals[1].')';
				}
			}

			return implode(', ', $value);
		}

		elseif ($GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['rgxp'] == 'date')
		{
			return $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $value);
		}

		elseif ($GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['rgxp'] == 'time')
		{
			return $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $value);
		}

		elseif ($GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['rgxp'] == 'datim' || in_array($GLOBALS['TL_DCA'][$table]['fields'][$field]['flag'], array(5, 6, 7, 8, 9, 10)) || $field == 'tstamp')
		{
			return $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $value);
		}

		elseif ($GLOBALS['TL_DCA'][$table]['fields'][$field]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['multiple'])
		{
			return strlen($value) ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
		}

		elseif ($GLOBALS['TL_DCA'][$table]['fields'][$field]['inputType'] == 'textarea' && ($GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['allowHtml'] || $GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['preserveTags']))
		{
			return specialchars($value);
		}

		elseif (is_array($GLOBALS['TL_DCA'][$table]['fields'][$field]['reference']))
		{
			return isset($GLOBALS['TL_DCA'][$table]['fields'][$field]['reference'][$value]) ? ((is_array($GLOBALS['TL_DCA'][$table]['fields'][$field]['reference'][$value])) ? $GLOBALS['TL_DCA'][$table]['fields'][$field]['reference'][$value][0] : $GLOBALS['TL_DCA'][$table]['fields'][$field]['reference'][$value]) : $value;
		}

		elseif (is_array($GLOBALS['TL_DCA'][$table]['fields'][$field]['options']))
		{
			return isset($GLOBALS['TL_DCA'][$table]['fields'][$field]['options'][$value]) ? $GLOBALS['TL_DCA'][$table]['fields'][$field]['options'][$value] : $value;
		}

		return $value;
	}


	/**
	 * Format label (based on DC_Table::show(), Contao 2.9.0)
	 * @param  mixed
	 * @param  string
	 * @param  string
	 * @return string
	 */
	protected function formatLabel($table, $field)
	{
		if (count($GLOBALS['TL_DCA'][$table]['fields'][$field]['label']))
		{
			$label = is_array($GLOBALS['TL_DCA'][$table]['fields'][$field]['label']) ? $GLOBALS['TL_DCA'][$table]['fields'][$field]['label'][0] : $GLOBALS['TL_DCA'][$table]['fields'][$field]['label'];
		}
		else
		{
			$label = is_array($GLOBALS['TL_LANG']['MSC'][$field]) ? $GLOBALS['TL_LANG']['MSC'][$field][0] : $GLOBALS['TL_LANG']['MSC'][$field];
		}

		if (!strlen($label))
		{
			$label = $field;
		}

		return $label;
	}
}

