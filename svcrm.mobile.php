<?php
/**
 * @class  svcrmMobile
 * @author singleview(root@singleview.co.kr)
 * @brief  svcrmMobile class
 */
class svcrmMobile extends svcrmView
{
	function init()
	{
		$oModuleModel = &getModel('module');
		$oModuleConfig = $oModuleModel->getModuleConfig('svcrm');
		$template_path = sprintf("%sm.skins/%s/",$this->module_path, $oModuleConfig->mskin);
		if(!is_dir($template_path)||!$this->module_info->mskin) 
		{
			$oModuleConfig->mskin = 'default';
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $oModuleConfig->mskin);
		}
		$this->setTemplatePath($template_path);
	}
}
/* End of file svcrm.mobile.php */
/* Location: ./modules/svcrm/svcrm.mobile.php */