<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Yaml\Yaml;

#[Signature('momjobs:postman
    {--spec=docs/api/openapi.yaml : OpenAPI spec to read (relative to the project root or absolute)}
    {--output=docs/api/momjobs.postman_collection.json : Where to write the Postman v2.1 collection}')]
#[Description('Generate the Postman collection of the mobile API from docs/api/openapi.yaml')]
class GeneratePostmanCollection extends Command
{
    public const string EMPLOYER_LOGIN_ITEM_ID = 'loginEmployer';

    private const string LOGIN_OPERATION_ID = 'login';

    private const string BASE_URL = 'http://localhost/api/v1';

    private const string DEMO_PASSWORD = 'password';

    private const string CANDIDATE_EMAIL = 'marta@momjobs.test';

    private const string EMPLOYER_EMAIL = 'hr@zielonebiuro.test';

    private const array HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * Path parameter name => collection variable and its demo value. `token` (device push token) and `article`
     * (slug) get distinct names because `{{token}}` is the bearer token.
     *
     * @var array<string, array{variable: string, value: string, description: string}>
     */
    private const array PATH_VARIABLES = [
        'offer' => ['variable' => 'offerId', 'value' => '1', 'description' => 'Job offer id'],
        'candidate' => ['variable' => 'candidateId', 'value' => '1', 'description' => 'Candidate profile id (from an anonymous card)'],
        'profile' => ['variable' => 'profileId', 'value' => '1', 'description' => 'Candidate profile id (photo)'],
        'company' => ['variable' => 'companyId', 'value' => '1', 'description' => 'Company id'],
        'conversation' => ['variable' => 'conversationId', 'value' => '1', 'description' => 'Conversation id'],
        'invitation' => ['variable' => 'invitationId', 'value' => '1', 'description' => 'Invitation id (candidate invitation or team invitation)'],
        'pair' => ['variable' => 'pairId', 'value' => '1', 'description' => 'Job-share pair id'],
        'skill' => ['variable' => 'skillId', 'value' => '1', 'description' => 'Skill id'],
        'user' => ['variable' => 'userId', 'value' => '2', 'description' => 'User id of a team member'],
        'review' => ['variable' => 'reviewId', 'value' => '1', 'description' => 'Own pending review id'],
        'notification' => ['variable' => 'notificationId', 'value' => '', 'description' => 'Notification UUID from GET /notifications'],
        'article' => ['variable' => 'articleSlug', 'value' => 'rozmowa-w-ciazy', 'description' => 'Blog article slug'],
        'token' => ['variable' => 'deviceToken', 'value' => 'fcm-demo-token-123', 'description' => 'Push device token registered with POST /devices'],
    ];

    /**
     * Sample values for properties without a spec example, by property name.
     *
     * @var array<string, string>
     */
    private const array SAMPLE_STRINGS = [
        'email' => 'rekruterka@zielonebiuro.test',
        'ai_summary' => 'Rekruterka IT z 6-letnim doświadczeniem w procesach dla zespołów technologicznych.',
        'cv_text' => 'Rekruterka IT, 6 lat doświadczenia. Rekrutacja IT, onboarding, Excel.',
        'since' => '2026-10-01T00:00:00+00:00',
        'q' => 'rekrutacja',
        'location' => 'Kraków',
        'v' => '1',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $spec = [];

    /**
     * Collection variables used by the generated requests.
     *
     * @var array<string, array{key: string, value: string, description: string}>
     */
    private array $usedVariables = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specPath = $this->absolutePath((string) $this->option('spec'));
        $outputPath = $this->absolutePath((string) $this->option('output'));

        if (! File::exists($specPath)) {
            $this->components->error("Spec not found: {$specPath}");

            return self::FAILURE;
        }

        /** @var array<string, mixed> $spec */
        $spec = Yaml::parseFile($specPath);
        $this->spec = $spec;

        $collection = $this->collection();

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        $requestCount = collect($collection['item'])->sum(fn (array $folder): int => count($folder['item']));
        $this->components->info("Wrote {$requestCount} requests in ".count($collection['item'])." folders to {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * @return array{info: array<string, string>, auth: array<string, mixed>, variable: list<array<string, string>>, item: list<array{name: string, description: string, item: list<array<string, mixed>>}>}
     */
    private function collection(): array
    {
        $folders = [];

        foreach ($this->spec['paths'] ?? [] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }

                $tag = $operation['tags'][0] ?? 'Other';

                if (($operation['operationId'] ?? null) === self::LOGIN_OPERATION_ID) {
                    $folders[$tag][] = $this->loginItem($operation, 'Login as candidate ('.self::CANDIDATE_EMAIL.')', self::CANDIDATE_EMAIL, self::LOGIN_OPERATION_ID);
                    $folders[$tag][] = $this->loginItem($operation, 'Login as employer ('.self::EMPLOYER_EMAIL.')', self::EMPLOYER_EMAIL, self::EMPLOYER_LOGIN_ITEM_ID);

                    continue;
                }

                $folders[$tag][] = $this->item((string) $path, $method, $operation);
            }
        }

        $tagDescriptions = collect($this->nodes($this->spec['tags'] ?? null))->mapWithKeys(fn (array $tag): array => [$tag['name'] => $tag['description'] ?? '']);
        $knownVariableOrder = array_flip(array_column(self::PATH_VARIABLES, 'variable'));
        uksort($this->usedVariables, fn (string $left, string $right): int => [$knownVariableOrder[$left] ?? PHP_INT_MAX, $left] <=> [$knownVariableOrder[$right] ?? PHP_INT_MAX, $right]);

        $orderedTags = $tagDescriptions->keys()->merge(array_keys($folders))->unique()->filter(fn (string $tag): bool => isset($folders[$tag]));

        return [
            'info' => [
                '_postman_id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'momjobs-api-v1')->toString(),
                'name' => 'MomJobs mobile API v1',
                'description' => 'Generated from docs/api/openapi.yaml by `php artisan momjobs:postman` – do not edit by hand. '
                    .'Run "Auth / Login as candidate" or "Login as employer" first – the test script stores the bearer token in {{token}}. '
                    .'Candidate endpoints need the candidate token, Employer endpoints the employer token. '
                    .'Set the *Id variables to real ids from list responses.',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'auth' => ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']]],
            'variable' => [
                ['key' => 'baseUrl', 'value' => self::BASE_URL, 'description' => 'API base URL'],
                ['key' => 'token', 'value' => '', 'description' => 'Bearer token, set by the login requests'],
                ...array_values($this->usedVariables),
            ],
            'item' => array_values($orderedTags->map(fn (string $tag): array => [
                'name' => $tag,
                'description' => (string) $tagDescriptions->get($tag, ''),
                'item' => $folders[$tag],
            ])->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function item(string $path, string $method, array $operation): array
    {
        $request = [
            'method' => strtoupper($method),
            'header' => [['key' => 'Accept', 'value' => 'application/json']],
            'url' => $this->url($path, $operation),
            'description' => trim(($operation['summary'] ?? '')."\n\n".($operation['description'] ?? '')),
        ];

        if (($operation['security'] ?? null) === []) {
            $request['auth'] = ['type' => 'noauth'];
        }

        $content = $operation['requestBody']['content'] ?? [];

        if (isset($content['multipart/form-data'])) {
            $request['body'] = ['mode' => 'formdata', 'formdata' => $this->formData($content['multipart/form-data']['schema'] ?? [])];
        } elseif (isset($content['application/json'])) {
            $request['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
            $request['body'] = $this->rawJsonBody($this->mediaExample($content['application/json']));
        }

        return [
            'id' => $operation['operationId'],
            'name' => $operation['summary'] ?? $operation['operationId'],
            'request' => $request,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function loginItem(array $operation, string $name, string $email, string $id): array
    {
        $body = $this->mediaExample($operation['requestBody']['content']['application/json'] ?? []);
        $body = array_merge(is_array($body) ? $body : [], ['email' => $email, 'password' => self::DEMO_PASSWORD, 'device_name' => 'Postman']);

        return [
            'id' => $id,
            'name' => $name,
            'event' => [[
                'listen' => 'test',
                'script' => ['type' => 'text/javascript', 'exec' => [
                    "pm.test('token issued', () => pm.response.to.have.status(200));",
                    "pm.collectionVariables.set('token', pm.response.json().token);",
                ]],
            ]],
            'request' => [
                'auth' => ['type' => 'noauth'],
                'method' => 'POST',
                'header' => [['key' => 'Accept', 'value' => 'application/json'], ['key' => 'Content-Type', 'value' => 'application/json']],
                'body' => $this->rawJsonBody($body),
                'url' => $this->url('/auth/login', $operation),
                'description' => 'Logs in and stores the token in the `token` collection variable (used by every authenticated request). '
                    .'Demo password: "'.self::DEMO_PASSWORD."\". Accounts with 2FA also need `code` or `recovery_code`.\n\n"
                    .($operation['description'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function url(string $path, array $operation): array
    {
        $parameters = collect($this->nodes($operation['parameters'] ?? null))->map(fn (array $parameter): array => $this->resolve($parameter));

        $postmanPath = preg_replace_callback('/\{(\w+)\}/', function (array $match) use ($parameters): string {
            $parameter = $parameters->first(fn (array $parameter): bool => $parameter['in'] === 'path' && $parameter['name'] === $match[1]) ?? [];

            return '{{'.$this->pathVariable($match[1], $parameter).'}}';
        }, trim($path, '/'));

        $query = $parameters
            ->filter(fn (array $parameter): bool => $parameter['in'] === 'query')
            ->map(fn (array $parameter): array => [
                'key' => $parameter['name'],
                'value' => $this->queryValue($parameter),
                'description' => $parameter['description'] ?? '',
                'disabled' => ! ($parameter['required'] ?? false),
            ])
            ->values()
            ->all();

        $raw = '{{baseUrl}}/'.$postmanPath;
        $enabledQuery = array_filter($query, fn (array $item): bool => ! $item['disabled']);

        if ($enabledQuery !== []) {
            $raw .= '?'.implode('&', array_map(fn (array $item): string => $item['key'].'='.$item['value'], $enabledQuery));
        }

        $url = ['raw' => $raw, 'host' => ['{{baseUrl}}'], 'path' => explode('/', (string) $postmanPath)];

        if ($query !== []) {
            $url['query'] = $query;
        }

        return $url;
    }

    /**
     * Registers (once) and returns the collection variable for a path parameter.
     *
     * @param  array<string, mixed>  $parameter
     */
    private function pathVariable(string $name, array $parameter): string
    {
        $known = self::PATH_VARIABLES[$name] ?? null;
        $variable = $known['variable'] ?? Str::camel($name).'Id';

        $this->usedVariables[$variable] ??= [
            'key' => $variable,
            'value' => $known['value'] ?? (string) ($parameter['example'] ?? '1'),
            'description' => $known['description'] ?? (string) ($parameter['description'] ?? "Path parameter {{$name}}"),
        ];

        return $variable;
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function queryValue(array $parameter): string
    {
        if ($parameter['name'] === 'page') {
            return '1';
        }

        $schema = $this->resolve($parameter['schema'] ?? []);

        if (($schema['type'] ?? null) === 'array') {
            $schema = $this->resolve($schema['items'] ?? []);
        }

        $value = $parameter['example'] ?? self::SAMPLE_STRINGS[$parameter['name']] ?? $this->example($schema, $parameter['name']);

        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    private function formData(array $schema): array
    {
        $schema = $this->resolve($schema);
        $required = $schema['required'] ?? [];
        $fields = [];

        foreach ($schema['properties'] ?? [] as $name => $property) {
            $property = $this->resolve($property);
            $description = $property['description'] ?? '';

            if (($property['format'] ?? null) === 'binary') {
                $fields[] = ['key' => $name, 'type' => 'file', 'src' => [], 'description' => $description];

                continue;
            }

            $value = $this->example($property, (string) $name);
            $fields[] = [
                'key' => $name,
                'type' => 'text',
                'value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                'description' => $description,
                'disabled' => ! in_array($name, $required, true),
            ];
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawJsonBody(mixed $body): array
    {
        return [
            'mode' => 'raw',
            'raw' => json_encode($body ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'options' => ['raw' => ['language' => 'json']],
        ];
    }

    /**
     * The media type example, its first named example, the schema example, or one generated from the schema.
     *
     * @param  array<string, mixed>  $media
     */
    private function mediaExample(array $media): mixed
    {
        if (array_key_exists('example', $media)) {
            return $media['example'];
        }

        if (isset($media['examples']) && $media['examples'] !== []) {
            $first = $this->resolve(reset($media['examples']));

            return $first['value'] ?? null;
        }

        return $this->example($this->resolve($media['schema'] ?? []));
    }

    /**
     * Builds an example value from a schema (explicit example → default → enum → generated by type).
     *
     * @param  array<string, mixed>  $schema
     */
    private function example(array $schema, string $propertyName = ''): mixed
    {
        $schema = $this->resolve($schema);

        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (isset(self::SAMPLE_STRINGS[$propertyName])) {
            return self::SAMPLE_STRINGS[$propertyName];
        }

        if (array_key_exists('default', $schema)) {
            return $schema['default'];
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $combinator) {
            if (isset($schema[$combinator])) {
                return $this->combinedExample($combinator, $schema[$combinator], $propertyName);
            }
        }

        $types = (array) ($schema['type'] ?? (isset($schema['properties']) ? 'object' : 'string'));
        $type = collect($types)->first(fn (string $type): bool => $type !== 'null') ?? 'null';

        return match ($type) {
            'object' => collect($this->nodes($schema['properties'] ?? null))
                ->map(fn (array $property, string $name): mixed => $this->example($property, $name))
                ->all() ?: new \stdClass,
            'array' => [$this->example($schema['items'] ?? [], Str::singular($propertyName))],
            'integer' => $schema['minimum'] ?? 1,
            'number' => $schema['minimum'] ?? 1.0,
            'boolean' => true,
            'null' => null,
            default => match ($schema['format'] ?? null) {
                'email' => 'user@example.com',
                'date' => '2027-09-01',
                'date-time' => '2026-10-01T00:00:00+00:00',
                'uuid' => '00000000-0000-0000-0000-000000000000',
                default => 'string',
            },
        };
    }

    /**
     * @param  list<array<string, mixed>>  $schemas
     */
    private function combinedExample(string $combinator, array $schemas, string $propertyName): mixed
    {
        if ($combinator !== 'allOf') {
            $nonNull = collect($schemas)->first(fn (array $schema): bool => ($this->resolve($schema)['type'] ?? null) !== 'null');

            return $this->example($nonNull ?? $schemas[0] ?? [], $propertyName);
        }

        $merged = [];

        foreach ($schemas as $schema) {
            $value = $this->example($schema, $propertyName);

            if (! is_array($value)) {
                return $value;
            }

            $merged = array_merge($merged, $value);
        }

        return $merged;
    }

    /**
     * Follows local `$ref`s (`#/components/...`).
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function resolve(array $node): array
    {
        while (isset($node['$ref'])) {
            $target = $this->spec;

            foreach (explode('/', ltrim(Str::after($node['$ref'], '#'), '/')) as $segment) {
                $target = $target[$segment] ?? [];
            }

            $node = array_merge(is_array($target) ? $target : [], array_diff_key($node, ['$ref' => true]));
        }

        return $node;
    }

    /**
     * A spec node holding a map or list of objects (tags, parameters, properties); anything else is empty.
     *
     * @return array<array-key, array<string, mixed>>
     */
    private function nodes(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
