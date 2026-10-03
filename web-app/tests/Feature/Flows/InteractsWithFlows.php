<?php

namespace Tests\Feature\Flows;

use Illuminate\Testing\TestResponse;

trait InteractsWithFlows
{
    /**
     * Called automatically by Laravel: pages render without a Vite build or an SSR server call, the offline
     * AI fallbacks are used and "today" is the demo date, so date rules (start dates, availability) are deterministic.
     */
    protected function setUpInteractsWithFlows(): void
    {
        $this->withoutVite();
        config(['services.anthropic.key' => null, 'inertia.ssr.enabled' => false]);
        $this->travelTo('2026-10-03 10:00:00');
    }

    /**
     * Everything the browser receives as Inertia page props, as one JSON string for privacy assertions.
     */
    protected function pagePropsJson(TestResponse $response): string
    {
        return (string) json_encode($response->viewData('page')['props'], JSON_UNESCAPED_UNICODE);
    }
}
