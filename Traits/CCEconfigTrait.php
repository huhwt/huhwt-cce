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
     * Filter on n_type
     *
     * @return array<int,string>
     */
    public function TAGconfigOptions(): array
    {
        return [
            0   => 'TAG',
            1   => 'CCE',
        ];
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'TAGoption'         => (int) $this->getPreference('TAG_Option', '0'),
            'TAG_options'       => $this->TAGconfigOptions(),
            'title'             => I18N::translate('Tagging preferences') . ' — ' . $this->title(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $TAGoption = Validator::parsedBody($request)->integer('TAGoption');

        $this->setPreference('TAG_Option', (string) $TAGoption);

        FlashMessages::addMessage(I18N::translate('The preferences for the module “%s” have been updated.', $this->title()), 'success');

        return redirect($this->getConfigLink());
    }


}