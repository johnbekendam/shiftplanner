<?php

namespace App\Mail\Concerns;

use Illuminate\Support\HtmlString;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

trait InlinesEmailStyles
{
    protected function buildView(): string|array
    {
        $viewName = parent::buildView();

        if (! is_string($viewName)) {
            return $viewName;
        }

        $html = view($viewName, $this->buildViewData())->render();
        $inlined = (new CssToInlineStyles)->convert($html);

        return ['html' => new HtmlString($inlined)];
    }
}
