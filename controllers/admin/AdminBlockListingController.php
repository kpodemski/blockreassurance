<?php
/**
 * 2007-2019 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2019 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

class AdminBlockListingController extends ModuleAdminController
{
    /**
     * Enable or disable a block
     *
     * @throws PrestaShopException
     */
    public function displayAjaxChangeBlockStatus()
    {
        $now = new DateTime();
        $psreassuranceId = (int)Tools::getValue('idpsr');
        $newStatus = ((int)Tools::getValue('status') == 1) ? 0 : 1;

        $dataToUpdate = [
            'status' => $newStatus,
            'date_upd' => $now->format('Y-m-d H:i:s'),
        ];
        $whereCondition = 'id_psreassurance = ' . $psreassuranceId;

        $updateResult = Db::getInstance()->update('psreassurance', $dataToUpdate, $whereCondition);

        // Response
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($updateResult ? 'success' : 'error'));
    }

    /**
     * Update how the blocks are displayed in the front-office
     *
     * @throws PrestaShopException
     */
    public function displayAjaxSavePositionByHook()
    {
        $hook = Tools::getValue('hook');
        $value = Tools::getValue('value');
        $result = false;

        if (!empty($hook) && in_array($value, array(
            blockreassurance::POSITION_NONE,
            blockreassurance::POSITION_BELOW_HEADER,
            blockreassurance::POSITION_ABOVE_HEADER,
        ))) {
            $result = Configuration::updateValue($hook, $value);
        }

        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($result ? 'success' : 'error'));
    }

    /**
     * Update color settings to be used in front-office display
     *
     * @throws PrestaShopException
     */
    public function displayAjaxSaveColor()
    {
        $color1 = Tools::getValue('color1');
        $color2 = Tools::getValue('color2');
        $result = false;

        if (!empty($color1) && !empty($color2)) {
            $result = Configuration::updateValue('PSR_ICON_COLOR', $color1)
                && Configuration::updateValue('PSR_TEXT_COLOR', $color2);
        }

        // Response
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($result ? 'success' : 'error'));
    }

    /**
     * Modify the settings of one block from BO "configure" page
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function displayAjaxSaveBlockContent()
    {
        $errors = [];

        $picto = Tools::getValue('picto');
        $id_block = Tools::getValue('id_block');
        $type_link = (int)Tools::getValue('typelink');
        $id_cms = Tools::getValue('id_cms');
        $psr_languages = (array)json_decode(Tools::getValue('lang_values'));

        $blockPsr = new ReassuranceActivity($id_block);
        $blockPsr->handleBlockValues($psr_languages, $type_link, $id_cms);
        $blockPsr->icon = $picto;
        if (empty($picto)) {
            $blockPsr->custom_icon = '';
        }
        $blockPsr->date_add = date("Y-m-d H:i:s");
        $blockPsr->date_update = date("Y-m-d H:i:s");

        if (isset($_FILES) && !empty($_FILES)) {
            $customImage = $_FILES['file'];
            $fileTmpName = $customImage['tmp_name'];
            $filename = $customImage['name'];

            // validateUpload return false if no error (false -> OK)
            $validUpload = ImageManager::validateUpload($customImage);
            if (is_bool($validUpload) && $validUpload === false) {
                move_uploaded_file($fileTmpName, $this->module->folder_file_upload . $filename);
                $blockPsr->custom_icon = $this->module->img_path_perso . '/' . $filename;
                $blockPsr->icon = '';
            } else {
                $errors[] = $validUpload;
            }
        }
        if (empty($errors)) {
            $blockPsr->update();
        }

        $this->ajaxRender(json_encode(empty($errors) ? 'success' : 'error'));
    }

    /**
     * Reorder the blocks positions
     */
    public function displayAjaxUpdatePosition()
    {
        $blocks = Tools::getValue('blocks');
        $result = false;

        if (!empty($blocks)) {
            foreach ($blocks as $key => $id_block) {
                // Set the position of the Reassurance block
                $position = $key + 1;

                $dataToUpdate = ['position' => (int)$position];
                $whereCondition = 'id_psreassurance = ' . (int)$id_block;
                $updateResult = (bool)Db::getInstance()->update('psreassurance', $dataToUpdate, $whereCondition);

                // If the update can't be done, we return false
                if (!$updateResult) {
                    break;
                }
            }
            $result = $updateResult ? true : false;
        }

        $this->ajaxRender(json_encode($result ? 'success' : 'error'));
    }
}
