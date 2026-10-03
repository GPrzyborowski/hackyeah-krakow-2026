<?php

namespace Tests\Feature\Api;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiSpecTest extends TestCase
{
    private const string SPEC_PATH = 'docs/api/openapi.yaml';

    private const string API_PREFIX = 'api/v1';

    private const array HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function test_every_api_route_is_documented_and_the_spec_has_no_stale_operations(): void
    {
        $routeOperations = $this->routeOperations();
        $specOperations = $this->specOperations();

        $this->assertNotEmpty($routeOperations);
        $this->assertSame([], array_values(array_diff($routeOperations, $specOperations)), 'API routes missing from '.self::SPEC_PATH);
        $this->assertSame([], array_values(array_diff($specOperations, $routeOperations)), self::SPEC_PATH.' documents operations that have no route');
    }

    public function test_spec_is_served_as_yaml(): void
    {
        $response = $this->get('/api/v1/openapi.yaml');

        $response->assertOk();
        $this->assertStringStartsWith('application/yaml', (string) $response->headers->get('Content-Type'));
        $this->assertSame('3.1.0', Yaml::parse((string) file_get_contents(base_path(self::SPEC_PATH)))['openapi']);
    }

    /**
     * "METHOD /path" for every api/v1 route, path parameters normalised to {}.
     *
     * @return list<string>
     */
    private function routeOperations(): array
    {
        $operations = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), self::API_PREFIX.'/'))
            ->flatMap(fn (RoutingRoute $route): array => collect($route->methods())
                ->map(fn (string $method): string => strtolower($method))
                ->intersect(self::HTTP_METHODS)
                ->map(fn (string $method): string => $method.' '.$this->normalisePath(substr($route->uri(), strlen(self::API_PREFIX))))
                ->all());

        return array_values($operations->unique()->sort()->all());
    }

    /**
     * @return list<string>
     */
    private function specOperations(): array
    {
        /** @var array{openapi: string, paths: array<string, array<string, mixed>>} $spec */
        $spec = Yaml::parse((string) file_get_contents(base_path(self::SPEC_PATH)));

        return array_values(collect($spec['paths'])
            ->flatMap(fn (array $operations, string $path): array => collect(array_keys($operations))
                ->intersect(self::HTTP_METHODS)
                ->map(fn (string $method): string => $method.' '.$this->normalisePath($path))
                ->all())
            ->unique()
            ->sort()
            ->all());
    }

    private function normalisePath(string $path): string
    {
        return '/'.trim((string) preg_replace('/\{[^}]+\}/', '{}', $path), '/');
    }
}
