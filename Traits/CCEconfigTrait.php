<?php

/**
 * HuH Extensions for webtrees
 * webtrees - clippings cart enhanced
 * Copyright (C) 2020-2023 EW.Heinrich
 * 
 * Coding for the configuration in Admin-Panel goes here
 */

declare(strict_types=1);

namespace HuHwt\WebtreesMods\ClippingsCartEnhanced\Traits;

use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

trait CCEconfigTrait {


    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $_line_endings      = $this->getPreference('line_endings', 'LF');
        $_separator         = $this->getPreference('separator', 'semi_colon');
        $_enclosure         = $this->getPreference('enclosure', 'none');
        $_escape            = $this->getPreference('escape', 'backslash');

        return $this->viewResponse($this->name() . '::settings', [
            'line_endings'  => $_line_endings,
            'separator'     => $_separator,
            'enclosure'     => $_enclosure,
            'escape'        => $_escape,
            'title'         => $this->title(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $exec_setting = function(ServerRequestInterface $request, string $key, string $default) {
            $_setting = Validator::parsedBody($request)->string($key, $default);
            $this->setPreference($key, $_setting);
        };
        $exec_setting($request, 'line_endings', 'LF');
        $exec_setting($request, 'separator', 'semi_colon');
        $exec_setting($request, 'enclosure', 'none');
        $exec_setting($request, 'escape', 'backslash');

        FlashMessages::addMessage(I18N::translate('The preferences for the module “%s” have been updated.', $this->title()), 'success');

        return redirect($this->getConfigLink());
    }


}