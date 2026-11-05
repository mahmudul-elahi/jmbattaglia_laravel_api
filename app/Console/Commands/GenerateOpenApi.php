<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

final class GenerateOpenApi extends Command
{
    protected $signature = 'portal:openapi {--output=public/openapi.json : Relative output path}';

    protected $description = 'Generate the API route inventory and request schemas from registered routes and Form Requests';

    public function handle(): int
    {
        $paths = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            [$controller,$action] = explode('@', $route->getActionName());
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $operation = ['summary' => $method.' /'.$route->uri(), 'operationId' => class_basename($controller).'_'.$action.'_'.strtolower($method).'_'.substr(hash('sha256', $route->uri()), 0, 8), 'tags' => [explode('/', $route->uri())[2]], 'parameters' => [], 'responses' => []];
                foreach ($route->parameterNames() as $name) {
                    $operation['parameters'][] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => in_array($name, ['id', 'bookingId'], true) && ! str_contains($route->uri(), 'packages') && ! str_contains($route->uri(), 'catalog') ? 'integer' : 'string']];
                }
                $middleware = $route->gatherMiddleware();
                $protected = in_array('auth:sanctum', $middleware, true);
                $operation['security'] = $protected ? [['bearerAuth' => []]] : [];
                $operation['x-required-permissions'] = array_values(array_map(fn ($entry) => substr($entry, 4), array_filter($middleware, fn ($entry) => str_starts_with($entry, 'can:'))));
                if ($route->uri() === 'api/v1/portal/order' || str_starts_with($route->uri(), 'api/v1/portal/order/')) {
                    $operation['parameters'][] = ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64], 'description' => 'Original random secret. Reuse it for retries and receipt access.'];
                }
                $reflection = new \ReflectionMethod($controller, $action);
                $rules = [];
                foreach ($reflection->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if (! $type instanceof \ReflectionNamedType || ! is_subclass_of($type->getName(), FormRequest::class)) {
                        continue;
                    }
                    $request = $type->getName()::create('/'.$route->uri(), $method);
                    $request->setRouteResolver(fn () => $route);
                    $request->setContainer($this->laravel);
                    $rules = $request->rules();
                    break;
                }
                $schema = $this->objectSchema($rules);
                if ($method === 'GET') {
                    foreach ($schema['properties'] as $name => $property) {
                        $operation['parameters'][] = ['name' => $name, 'in' => 'query', 'required' => in_array($name, $schema['required'] ?? [], true), 'schema' => $property];
                    }
                } elseif ((array) $schema['properties']) {
                    $content = ['application/json' => ['schema' => $schema]];
                    if (str_contains($route->uri(), 'media') || str_contains($route->uri(), 'profile') || str_contains($route->uri(), 'addons')) {
                        $content['multipart/form-data'] = ['schema' => $schema];
                    }
                    $operation['requestBody'] = ['required' => true, 'content' => $content];
                }
                $success = $action === 'submit' ? 202 : ($action === 'store' || $action === 'register' || $action === 'upload' || $action === 'signed' || $action === 'createRule' ? 201 : 200);
                if ($action === 'retired') {
                    $operation['deprecated'] = true;
                    $success = 410;
                }
                foreach (array_unique([$success, 400, 401, 403, 404, 409, 429, 503]) as $status) {
                    $dataSchema = $this->responseData(class_basename($controller), $action);
                    $responseSchema = ['$ref' => '#/components/schemas/'.($status < 400 ? 'Success' : 'Error')];
                    if ($status < 400 && $dataSchema) {
                        $responseSchema = ['allOf' => [$responseSchema, ['type' => 'object', 'properties' => ['data' => $dataSchema]]]];
                    }
                    $operation['responses'][(string) $status] = ['description' => Response::$statusTexts[$status], 'content' => ['application/json' => ['schema' => $responseSchema]]];
                }
                $paths['/'.$route->uri()][strtolower($method)] = $operation;
            }
        }
        ksort($paths);
        $document = ['openapi' => '3.1.0', 'info' => ['title' => 'At Ease API', 'version' => '1.0.0', 'description' => 'Integer local IDs, provider string IDs, expiring Sanctum bearer tokens, and no refresh endpoint. Booking submissions remain requests awaiting staff confirmation.'], 'servers' => [['url' => '/']], 'paths' => $paths, 'components' => ['securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']], 'schemas' => [
            'Success' => ['type' => 'object', 'required' => ['success', 'statusCode', 'message', 'data'], 'properties' => ['success' => ['const' => true], 'statusCode' => ['type' => 'integer'], 'message' => ['type' => 'string'], 'data' => (object) []]],
            'Error' => ['type' => 'object', 'required' => ['success', 'statusCode', 'message', 'timestamp'], 'properties' => ['success' => ['const' => false], 'statusCode' => ['type' => 'integer'], 'message' => ['type' => 'array', 'items' => ['type' => 'string']], 'timestamp' => ['type' => 'string', 'format' => 'date-time']]],
        ]]];
        $document['components']['schemas'] += $this->responseSchemas();
        $target = base_path($this->option('output'));
        file_put_contents($target, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->info('Generated '.count($paths).' API paths.');

        return self::SUCCESS;
    }

    private function responseData(string $controller, string $action): ?array
    {
        $name = match (true) {
            $controller === 'AuthController' && $action === 'login' => 'LoginData',
            $controller === 'AuthController' && in_array($action, ['me', 'register'], true) => 'User',
            $controller === 'OrderingController' && in_array($action, ['submit', 'receipt'], true) => 'Receipt',
            $controller === 'OrderingController' && $action === 'quote' => 'Quote',
            $controller === 'OrderingController' && $action === 'availability' => 'Availability',
            $controller === 'SettingsController' => 'PortalSettings',
            default => null,
        };

        return $name ? ['$ref' => '#/components/schemas/'.$name] : null;
    }

    private function responseSchemas(): array
    {
        $string = ['type' => 'string'];
        $integer = ['type' => 'integer'];
        $nullableString = ['type' => ['string', 'null']];
        $list = fn (array $item): array => ['type' => 'array', 'items' => $item];
        $object = fn (array $properties): array => ['type' => 'object', 'properties' => $properties];
        $user = $object(['id' => $integer, 'email' => ['type' => 'string', 'format' => 'email'], 'name' => $string, 'fullName' => $string, 'firstName' => $nullableString, 'lastName' => $nullableString, 'avatarUrl' => $nullableString, 'isEmailVerified' => ['type' => 'boolean'], 'role' => $nullableString, 'roles' => $list($string), 'permissions' => $list($string), 'profile' => ['type' => ['object', 'null']]]);
        $receipt = $object(['id' => $integer, 'orderId' => $integer, 'reference' => $string, 'status' => ['type' => 'string', 'enum' => ['PROCESSING', 'SUBMITTED', 'FAILED', 'REQUIRES_REVIEW']], 'appointmentStatus' => ['const' => 'AWAITING_STAFF_CONFIRMATION'], 'notificationStatus' => ['type' => 'string', 'enum' => ['SENT', 'PENDING', 'NOT_QUEUED']], 'date' => $string, 'time' => $string, 'timezone' => $string, 'totalCents' => $integer, 'currency' => ['const' => 'USD'], 'isnOrderId' => $nullableString, 'isnOrderNumber' => ['type' => ['integer', 'null']], 'notificationPending' => ['type' => 'boolean'], 'notificationSentAt' => ['type' => ['string', 'null'], 'format' => 'date-time'], 'errorCode' => $nullableString, 'message' => $string]);
        $quote = $object(['quoteId' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'], 'totalCents' => $integer, 'durationMinutes' => $integer, 'serviceIds' => $list($string), 'packageId' => $nullableString, 'orderTypeId' => $string, 'lines' => $list($object(['id' => ['type' => ['string', 'integer']], 'name' => $string, 'amountCents' => $integer])), 'expiresAt' => ['type' => 'string', 'format' => 'date-time'], 'currency' => ['const' => 'USD'], 'timezone' => $string, 'pricingStatus' => ['const' => 'ESTIMATE']]);

        return ['User' => $user, 'LoginData' => $object(['accessToken' => $string, 'tokenType' => ['const' => 'Bearer'], 'expiresIn' => $integer, 'expiresAt' => ['type' => 'string', 'format' => 'date-time'], 'user' => ['$ref' => '#/components/schemas/User']]), 'Receipt' => $receipt, 'Quote' => $quote, 'Availability' => $object(['quoteId' => $string, 'timezone' => $string, 'appointments' => $list($object(['date' => $string, 'time' => $string, 'inspectorId' => $string, 'inspectorName' => $string, 'durationMinutes' => $integer]))]), 'PortalSettings' => $object(['brandName' => $string, 'helpPhone' => $string, 'welcomeTitle' => $string, 'pricingDisclaimer' => $string, 'confirmationMessage' => $string])];
    }

    private function objectSchema(array $rules, string $prefix = ''): array
    {
        $schema = ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
        $required = [];
        foreach ($rules as $key => $definition) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }
            $name = substr($key, strlen($prefix));
            if (str_contains($name, '.') || $name === '*') {
                continue;
            }
            $parts = is_string($definition) ? explode('|', $definition) : $definition;
            if (in_array('prohibited', $parts, true)) {
                continue;
            }
            $property = ['type' => 'string'];
            foreach ($parts as $rule) {
                if (! is_string($rule)) {
                    continue;
                }
                if ($rule === 'integer' || str_starts_with($rule, 'integer:')) {
                    $property['type'] = 'integer';
                } elseif ($rule === 'boolean' || str_starts_with($rule, 'boolean:')) {
                    $property['type'] = 'boolean';
                } elseif ($rule === 'array' || str_starts_with($rule, 'array:')) {
                    $childPrefix = $key.'.';
                    $hasItems = array_key_exists($childPrefix.'*', $rules);
                    $property = $hasItems ? ['type' => 'array', 'items' => $this->itemSchema($rules[$childPrefix.'*'])] : $this->objectSchema($rules, $childPrefix);
                    if (! $hasItems && count((array) $property['properties']) === 0 && ! str_starts_with($rule, 'array:')) {
                        $property = ['type' => 'array', 'items' => (object) []];
                    }
                } elseif ($rule === 'file' || $rule === 'image') {
                    $property = ['type' => 'string', 'format' => 'binary'];
                } elseif ($rule === 'email') {
                    $property['format'] = 'email';
                } elseif (str_starts_with($rule, 'url')) {
                    $property['format'] = 'uri';
                } elseif (str_starts_with($rule, 'in:')) {
                    $property['enum'] = explode(',', substr($rule, 3));
                } elseif ($rule === 'nullable') {
                    $property['nullable'] = true;
                }
            }
            if (isset($property['nullable'])) {
                unset($property['nullable']);
                $property['type'] = array_merge((array) $property['type'], ['null']);
                if (isset($property['enum'])) {
                    $property['enum'][] = null;
                }
            }
            foreach ($parts as $rule) {
                if (! is_string($rule) || ! preg_match('/^(min|max|size):([0-9]+)$/', $rule, $constraint) || ($property['format'] ?? null) === 'binary') {
                    continue;
                }
                $kind = in_array('array', (array) $property['type'], true) ? 'Items' : (in_array('integer', (array) $property['type'], true) ? 'imum' : 'Length');
                if ($constraint[1] === 'size') {
                    if ($kind !== 'imum') {
                        $property['min'.$kind] = (int) $constraint[2];
                        $property['max'.$kind] = (int) $constraint[2];
                    }
                } else {
                    $property[$constraint[1].$kind] = (int) $constraint[2];
                }
            }
            if (in_array('required', $parts, true)) {
                $required[] = $name;
            }
            $schema['properties'][$name] = $property;
        }
        if ($required) {
            $schema['required'] = $required;
        }
        $schema['properties'] = (object) $schema['properties'];

        return $schema;
    }

    private function itemSchema(mixed $definition): array
    {
        $parts = is_string($definition) ? explode('|', $definition) : $definition;

        return ['type' => in_array('integer', $parts, true) ? 'integer' : (in_array('string', $parts, true) ? 'string' : ['string', 'integer'])];
    }
}
