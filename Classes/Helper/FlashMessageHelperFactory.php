<?php
namespace Networkteam\Neos\FrontendLogin\Helper;

use Neos\Flow\Mvc\ActionRequest;

/***************************************************************
 *  (c) 2021 networkteam GmbH - all rights reserved
 ***************************************************************/

/**
 * Class FlashMessageHelperFactory
 *
 * @package Networkteam\Neos\FrontendLogin\Helper
 */
class FlashMessageHelperFactory
{

    public static function create(ActionRequest $request): FlashMessageHelper
    {
        return new FlashMessageHelper($request);
    }
}