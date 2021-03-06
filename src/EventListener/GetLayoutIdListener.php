<?php
/*
 * Posts'n'pages extension for Contao Open Source CMS.
 *
 * @copyright  Arne Stappen (alias aGoat) 2021
 * @package    contao-postsnpages
 * @author     Arne Stappen <mehh@agoat.xyz>
 * @link       https://agoat.xyz
 * @license    LGPL-3.0
 */

namespace Agoat\PostsnPagesBundle\EventListener;

use Agoat\PostsnPagesBundle\Model\ArchiveModel;
use Agoat\PostsnPagesBundle\Model\ContainerModel;
use Agoat\PostsnPagesBundle\Model\PostModel;
use Agoat\PostsnPagesBundle\Model\StaticModel;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\PageModel;

/**
 * @Hook("getLayoutId")
 */
class GetLayoutIdListener
{

    /**
     * Get the layout ID
     *
     * @param  string  $strTable
     * @param  integer  $intId
     *
     * @return integer The theme ID
     */
    public function __invoke(string $table, ?int $id)
    {
        if ($id === null) {
            return null;
        }

        if ('tl_post' == $table) {
            $objPost = PostModel::findByPk($id);

            if ($objPost === null) {
                return null;
            }

            $objArchive = ArchiveModel::findByPk($objPost->pid);

            if ($objArchive === null) {
                return null;
            }

            $objPage = PageModel::findWithDetails($objArchive->pid);

            if ($objPage === null) {
                return null;
            }

            return $objPage->layout;
        } elseif ('tl_container' == $table) {
            $objContainer = ContainerModel::findByPk($id);

            if ($objContainer === null) {
                return null;
            }

            $objPage = PageModel::findWithDetails($objContainer->pid);

            if ($objPage === null) {
                return null;
            }

            return $objPage->layout;
        } elseif ('tl_static' == $table) {
            $objStatic = StaticModel::findByPk($id);

            if ($objStatic === null) {
                return null;
            }

            return $objStatic->layout;
        }
    }

}
