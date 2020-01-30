<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    UnaBaseView UNA Base Representation Classes
 * @{
 */

/**
 * Services for Wiki functionality
 */
class BxBaseServiceWiki extends BxDol
{
    protected $_bJsCssAdded = false;

    /**
     * @page service Service Calls
     * @section bx_system_general System Services 
     * @subsection bx_system_general-wiki Wiki
     * @subsubsection bx_system_general-wiki_page wiki_page
     * 
     * @code bx_srv('system', 'wiki_page', ["index"], 'TemplServiceWiki'); @endcode
     * @code {{~system:wiki_page:TemplServiceWiki["index"]~}} @endcode
     * 
     * Display WIKI page.
     * @param $sUri categories object name
     * 
     * @see BxBaseServiceWiki::serviceWikiPage
     */
    /** 
     * @ref bx_system_general-wiki_page "wiki_page"
     */
    public function serviceWikiPage ($sWikiObjectUri, $sUri)
    {
        $oWiki = BxDolWiki::getObjectInstanceByUri($sWikiObjectUri);
        if (!$oWiki) {
            $oTemplate = BxDolTemplate::getInstance();
            $oTemplate->displayPageNotFound();
            return;
        }

        $oPage = BxDolPage::getObjectInstanceByModuleAndURI($oWiki->getObjectName(), $sUri);
        if ($oPage) {

            $oPage->displayPage();

        } else {

            if ($oWiki->isAllowed('add')) {
                $oPage = BxDolPage::getObjectInstanceByURI($sUri);
                if ($oPage) {
                    $oTemplate = BxDolTemplate::getInstance();
                    $oTemplate->displayErrorOccured(_t("_sys_wiki_error_page_exists", bx_process_output($sUri)));
                } else {
                    echo "TODO: wiki - suggest to create page with specified URI. Display form where user can enter title(text input), text(plain textrea), languale (selectbox with currect site languages with current one pre-selected), revision comments (text input), title - will be page and first block title, text - will be content for first block. Default layout is page with one column and block without borders and title.";
                }
            } 
            else {
                $oTemplate = BxDolTemplate::getInstance();
                $oTemplate->displayPageNotFound();
            }
        }

    }

    /**
     * @page service Service Calls
     * @section bx_system_general System Services 
     * @subsection bx_system_general-wiki Wiki
     * @subsubsection bx_system_general-wiki_action wiki_action
     * 
     * @code bx_srv('system', 'wiki_action', [...], 'TemplServiceWiki'); @endcode
     * 
     * Perform WIKI action.
     * @param $sWikiObjectUri wiki object URI
     * 
     * @see BxBaseServiceWiki::serviceWikiAction
     */
    /** 
     * @ref bx_system_general-wiki_action "wiki_action"
     */
    public function serviceWikiAction ($sWikiObjectUri, $sAction)
    {
        $oWiki = BxDolWiki::getObjectInstanceByUri($sWikiObjectUri);
        if (!$oWiki) {
            echoJson(array('code' => 1, 'actions' => 'ShowMsg', 'msg' => _t('_sys_wiki_error_missing_wiki_object', $sWikiObjectUri)));
            return;
        }

        $sMethod = 'action' . bx_gen_method_name($sAction, array('-'));
        if (!method_exists($oWiki, $sMethod) || !$oWiki->isAllowed($sAction)) {            
            echoJson(array('code' => 2, 'actions' => 'ShowMsg', 'msg' => _t('_sys_wiki_error_action_not_allowed', $sAction, $sWikiObjectUri)));
            return;
        }
        
        $mixed = $oWiki->$sMethod();
        if (is_array($mixed))
            echoJson($mixed);
        else
            echo $mixed;
    }

    /**
     * @page service Service Calls
     * @section bx_system_general System Services 
     * @subsection bx_system_general-wiki Wiki
     * @subsubsection bx_system_general-wiki_controls wiki_controls
     * 
     * Get wiki block controls panel
     * 
     * @see BxBaseServiceWiki::serviceWikiControls
     */
    /** 
     * @ref bx_system_general-wiki_controls "wiki_controls"
     */
    public function serviceWikiControls ($oWikiObject, $aWikiVer, $aWikiVerLatest, $sBlockId)
    {
        $this->_addCssJs ();
    
        if (!($oMenu = BxDolMenu::getObjectInstance('sys_wiki')))
            return '';

        $oMenu->setMenuObject($oWikiObject);

        $sInfo = '';
        if ($aWikiVer && $aWikiVerLatest['revision'] == $aWikiVer['revision']) {
            $sInfo = bx_time_js($aWikiVer['added']);
        } 
        elseif ($aWikiVer) {
            $oProfile = BxDolProfile::getInstanceMagic($aWikiVer['profile_id']);
            $sInfo = _t('_sys_wiki_view_rev', $aWikiVer['revision'], $oProfile->getUrl(), $oProfile->getDisplayName(), bx_time_js($aWikiVer['added']));
        }

        $o = BxDolTemplate::getInstance();        
        return $o->parseHtmlByName('wiki_controls.html', array(
            'obj' => $oWikiObject->getObjectName(),
            'block_id' => $sBlockId,
            'menu' => $oMenu->getCode(),
            // 'TODO: On right - Last modified time and List of missing and outdated translations. History and Last modified time should be controlled by regular menu privacy, while other actions should have custom privacy for particular wiki object',
            'info' => $sInfo,
            'options' => json_encode(array(
                'block_id' => $sBlockId,
                'language' => isset($aWikiVer['language']) ? $aWikiVer['language'] : bx_lang_name(),
                'wiki_action_uri' => $oWikiObject->getWikiUri(),
                't_confirm_block_deletion' => _t('_sys_wiki_confirm_block_deletion'),
            )),
        ));
    }

    /**
     * @page service Service Calls
     * @section bx_system_general System Services 
     * @subsection bx_system_general-wiki Wiki
     * @subsubsection bx_system_general-wiki_add_block wiki_add_block
     * 
     * Get "add wiki block" panel
     * 
     * @see BxBaseServiceWiki::serviceWikiAddBlock
     */
    /** 
     * @ref bx_system_general-wiki_add_block "wiki_add_block"
     */
    public function serviceWikiAddBlock ($oWikiObject, $sPageObject, $sCellId)
    {
        $this->_addCssJs ();
        if (!preg_match("/cell_(\d+)/", $sCellId, $aMatches))
            return '';
        $iCellId = $aMatches[1];

        $o = BxDolTemplate::getInstance();        
        return $o->parseHtmlByName('wiki_add_block.html', array(
            'add_block' => _t('_sys_wiki_add_block'),
            'page' => $sPageObject,
            'cell_id' => $iCellId,
            'action_uri' => $oWikiObject->getWikiUri(),
        ));
    }

    /**
     * @page service Service Calls
     * @section bx_system_general System Services 
     * @subsection bx_system_general-wiki Wiki
     * @subsubsection bx_system_general-get_design_boxes get_design_boxes
     * 
     * Get "design boxes" array
     * 
     * @see BxBaseServiceWiki::serviceGetDesignBoxes
     */
    /** 
     * @ref bx_system_general-get_design_boxes "get_design_boxes"
     */
    public function serviceGetDesignBoxes ()
    {
        $o = new BxDolStudioBuilderPageQuery();
        $aItems = array();
        if (!$o->getDesignBoxes(array('type' => 'all'), $aItems, false))
            return array();
        $a = array();
        foreach($aItems as $r)
            $a[$r['id']] = _t($r['title']);
        return $a;
    }

    protected function _addCssJs ()
    {
        if ($this->_bJsCssAdded)
            return false;

        $o = BxDolTemplate::getInstance();
        $o->addCss('wiki.css');
        $o->addJs('BxDolWiki.js');
        $this->_bJsCssAdded = true;
    }
}

/** @} */
