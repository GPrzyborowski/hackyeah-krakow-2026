<?php

namespace Tests\Feature\Console;

use App\Console\Commands\GeneratePostmanCollection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class GeneratePostmanCollectionTest extends TestCase
{
    private const array HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = storage_path('framework/testing/postman-'.uniqid().'.json');
    }

    protected function tearDown(): void
    {
        File::delete($this->outputPath);

        parent::tearDown();
    }

    public function test_every_spec_operation_appears_exactly_once_in_the_collection(): void
    {
        $this->artisan('mumjobs:postman', ['--output' => $this->outputPath])->assertSuccessful();

        $collection = json_decode((string) file_get_contents($this->outputPath), true, flags: JSON_THROW_ON_ERROR);
        $items = collect($collection['item'])->flatMap(fn (array $folder): array => $folder['item'])->keyBy('id');

        $specOperations = collect(Yaml::parseFile(base_path('docs/api/openapi.yaml'))['paths'])
            ->flatMap(fn (array $operations, string $path): array => collect($operations)
                ->only(self::HTTP_METHODS)
                ->map(fn (array $operation, string $method): array => [
                    'id' => $operation['operationId'],
                    'method' => strtoupper($method),
                    'path' => trim((string) preg_replace('/\{\w+\}/', '{}', $path), '/'),
                ])
                ->values()
                ->all());

        $this->assertSame(
            $specOperations->pluck('id')->push(GeneratePostmanCollection::EMPLOYER_LOGIN_ITEM_ID)->sort()->values()->all(),
            $items->keys()->sort()->values()->all(),
        );

        foreach ($specOperations as $operation) {
            $request = $items[$operation['id']]['request'];

            $this->assertSame($operation['method'], $request['method'], $operation['id']);
            $this->assertSame($operation['path'], preg_replace('/\{\{\w+\}\}/', '{}', implode('/', $request['url']['path'])), $operation['id']);
        }
    }

    public function test_login_requests_store_the_token_and_public_endpoints_skip_auth(): void
    {
        $this->artisan('mumjobs:postman', ['--output' => $this->outputPath])->assertSuccessful();

        $collection = json_decode((string) file_get_contents($this->outputPath), true, flags: JSON_THROW_ON_ERROR);
        $items = collect($collection['item'])->flatMap(fn (array $folder): array => $folder['item'])->keyBy('id');

        $this->assertSame('bearer', $collection['auth']['type']);
        $this->assertSame('noauth', $items['publicOffers']['request']['auth']['type']);
        $this->assertArrayNotHasKey('auth', $items['me']['request']);

        foreach (['login' => 'marta@mumjobs.test', GeneratePostmanCollection::EMPLOYER_LOGIN_ITEM_ID => 'hr@zielonebiuro.test'] as $id => $email) {
            $this->assertSame($email, json_decode($items[$id]['request']['body']['raw'], true)['email']);
            $this->assertContains("pm.collectionVariables.set('token', pm.response.json().token);", $items[$id]['event'][0]['script']['exec']);
        }
    }
}
