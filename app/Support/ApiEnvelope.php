<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One response shape for the JSON APIs:
 *
 *   { "success": true|false, "message": "...", "data": ... }
 *
 * - Lists that were paginated come back as  data: { items, meta, links }.
 * - Failures are  { success: false, message, data: null, errors? } — `errors` is the per-field
 *   validation map (field => [messages]); any other extra the controller sent (e.g. `redirect`)
 *   moves into `data`.
 *
 * Applies to every /api/* route except the third-party webhooks in EXCLUDED.
 */
class ApiEnvelope
{
    /** Request paths (Request::is patterns) that get the envelope. */
    public const PATHS = ['api/*'];

    /** Never wrapped, even if a PATHS pattern matches (third parties expect their own reply). */
    public const EXCLUDED = ['api/stripe/webhook', 'api/webhooks/*'];

    public static function applies(Request $request): bool
    {
        return $request->is(...self::PATHS) && ! $request->is(...self::EXCLUDED);
    }

    /** Re-shape an already-built JSON reply (success or failure) into the envelope. */
    public static function wrap(JsonResponse $response, ?Request $request = null): JsonResponse
    {
        $body = $response->getData(true);
        $status = $response->getStatusCode();

        if (is_array($body) && self::isEnveloped($body)) {
            return $response;
        }

        $body = is_array($body) ? $body : ['data' => $body];

        $response->setData($status >= 400
            ? self::failure($body, $status)
            : self::success($body, $request?->method() ?? 'GET', $status, $request ? self::subject($request) : ''));

        return $response;
    }

    public static function success(array $body, string $method = 'GET', int $status = 200, string $subject = ''): array
    {
        $success = is_bool($body['success'] ?? null) ? $body['success'] : true;
        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : self::defaultMessage($method, $status, $subject);
        unset($body['success'], $body['message']);

        if (array_is_list($body)) {
            $data = $body;
        } elseif (array_key_exists('data', $body) && (array_key_exists('meta', $body) || array_key_exists('links', $body))) {
            // A paginated resource collection: {data: [...], links, meta}.
            $data = [
                'items' => $body['data'],
                'meta' => $body['meta'] ?? null,
                'links' => $body['links'] ?? null,
            ];
            $extra = array_diff_key($body, array_flip(['data', 'meta', 'links']));
            $data += $extra;
        } elseif (array_keys($body) === ['data']) {
            $data = $body['data'];
        } else {
            $data = $body ?: null;
        }

        return ['success' => $success, 'message' => $message, 'data' => $data];
    }

    public static function failure(array $body, int $status): array
    {
        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : (\Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Error');
        // Eloquent's "No query results for model [AppModelsLead] 12" is for logs, not for people.
        if ($status === 404 && str_starts_with($message, 'No query results')) {
            $message = 'The requested record was not found.';
        }
        $errors = $body['errors'] ?? null;
        unset($body['success'], $body['message'], $body['errors']);

        $out = ['success' => false, 'message' => $message, 'data' => $body ?: null];
        if ($errors !== null) {
            $out['errors'] = $errors;
        }

        return $out;
    }

    /** What the request is about, from its URL — "api/crm/lead-imports/12" → "Lead imports". */
    private static function subject(Request $request): string
    {
        $segments = array_filter($request->segments(), fn ($s) => ! in_array($s, ['api', 'crm'], true) && ! preg_match('/^[0-9a-f-]*d[0-9a-f-]*$/i', $s));

        return $segments ? ucfirst(str_replace(['-', '_'], ' ', (string) end($segments))) : 'Request';
    }

    private static function defaultMessage(string $method, int $status, string $subject): string
    {
        $subject = $subject !== '' ? $subject : 'Request';

        return match (true) {
            $method === 'GET' || $method === 'HEAD' => "$subject retrieved successfully.",
            $method === 'DELETE' => "$subject deleted successfully.",
            $method === 'PUT' || $method === 'PATCH' => "$subject updated successfully.",
            $status === 201 => "$subject created successfully.",
            default => 'Request completed successfully.',
        };
    }

    private static function isEnveloped(array $body): bool
    {
        return is_bool($body['success'] ?? null)
            && array_key_exists('message', $body)
            && array_key_exists('data', $body)
            && count(array_diff(array_keys($body), ['success', 'message', 'data', 'errors'])) === 0;
    }
}
