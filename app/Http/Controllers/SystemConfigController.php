<?php

namespace App\Http\Controllers;

use App\Models\SystemRevenue;
use App\Service\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    public function __construct(
        protected SystemConfigService $systemConfigService,
    ) {

    }

    public function getFee(): JsonResponse
    {
        try {
            return response()->json([
                'fee_type' => $this->systemConfigService->getFeeType(),
                'fee_value' => $this->systemConfigService->getFeeValue(),
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function updateFee(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fee_type' => 'required|in:fixed,percentage',
                'fee_value' => 'required|integer|min:1',
            ]);

            $this->systemConfigService->set('fee_type', $request->input('fee_type'));
            $this->systemConfigService->set('fee_value', (string) $request->input('fee_value'));

            return response()->json([
                'message' => __('messages.system_config.fee_updated'),
                'fee_type' => $request->input('fee_type'),
                'fee_value' => $request->input('fee_value'),
            ], JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                ['message' => $e->getMessage(), 'errors' => $e->errors()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function getPaymentGateway(): JsonResponse
    {
        try {
            return response()->json([
                'payment_gateway' => $this->systemConfigService->getPaymentGateway(),
                'available' => SystemConfigService::PAYMENT_GATEWAYS,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function updatePaymentGateway(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_gateway' => 'required|in:' . implode(',', SystemConfigService::PAYMENT_GATEWAYS),
            ]);

            $this->systemConfigService->setPaymentGateway($validated['payment_gateway']);

            return response()->json([
                'message' => __('messages.system_config.payment_gateway_updated'),
                'payment_gateway' => $validated['payment_gateway'],
            ], JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                ['message' => $e->getMessage(), 'errors' => $e->errors()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Admin: lists, for every gateway, which methods it supports and which are
     * currently enabled, plus the active gateway.
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $active = $this->systemConfigService->getPaymentGateway();

            $gateways = [];
            foreach (SystemConfigService::PAYMENT_GATEWAYS as $gateway) {
                $gateways[$gateway] = [
                    'supported' => $this->systemConfigService->getSupportedMethods($gateway),
                    'enabled' => $this->systemConfigService->getEnabledMethods($gateway),
                ];
            }

            return response()->json([
                'active_gateway' => $active,
                'gateways' => $gateways,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Admin: enables/disables payment methods for a specific gateway. Only the
     * methods the gateway supports are accepted, and at least one must stay on.
     */
    public function updatePaymentMethods(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'gateway' => 'required|in:' . implode(',', SystemConfigService::PAYMENT_GATEWAYS),
                'methods' => 'required|array|min:1',
                'methods.*' => 'string|in:pix,boleto,card',
            ]);

            $this->systemConfigService->setEnabledMethods($validated['gateway'], $validated['methods']);

            return response()->json([
                'message' => __('messages.system_config.payment_methods_updated'),
                'gateway' => $validated['gateway'],
                'enabled' => $this->systemConfigService->getEnabledMethods($validated['gateway']),
            ], JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                ['message' => $e->getMessage(), 'errors' => $e->errors()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Admin: current environment (sandbox/production) for each gateway, plus
     * the active gateway and its environment.
     */
    public function getPaymentEnvironment(): JsonResponse
    {
        try {
            $environments = [];
            foreach (SystemConfigService::PAYMENT_GATEWAYS as $gateway) {
                $environments[$gateway] = $this->systemConfigService->getEnvironment($gateway);
            }

            return response()->json([
                'available' => SystemConfigService::PAYMENT_ENVIRONMENTS,
                'active_gateway' => $this->systemConfigService->getPaymentGateway(),
                'active_environment' => $this->systemConfigService->getActiveGatewayEnvironment(),
                'environments' => $environments,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Admin: switches a gateway between sandbox and production for testing.
     */
    public function updatePaymentEnvironment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'gateway' => 'required|in:' . implode(',', SystemConfigService::PAYMENT_GATEWAYS),
                'environment' => 'required|in:' . implode(',', SystemConfigService::PAYMENT_ENVIRONMENTS),
            ]);

            $this->systemConfigService->setEnvironment($validated['gateway'], $validated['environment']);

            return response()->json([
                'message' => __('messages.system_config.payment_environment_updated'),
                'gateway' => $validated['gateway'],
                'environment' => $validated['environment'],
            ], JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                ['message' => $e->getMessage(), 'errors' => $e->errors()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    /**
     * Public (authenticated) endpoint used by the frontend to render the
     * payment screen: the methods available on the active gateway right now,
     * plus the environment so the client can load the matching Efí JS SDK.
     */
    public function availablePaymentMethods(): JsonResponse
    {
        try {
            return response()->json([
                'gateway' => $this->systemConfigService->getPaymentGateway(),
                'environment' => $this->systemConfigService->getActiveGatewayEnvironment(),
                'methods' => $this->systemConfigService->getEnabledMethodsForActiveGateway(),
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function getRevenue(Request $request): JsonResponse
    {
        try {
            $query = SystemRevenue::query();

            if ($request->has('date_start') && $request->input('date_start')) {
                $query->whereDate('created_at', '>=', $request->input('date_start'));
            }

            if ($request->has('date_end') && $request->input('date_end')) {
                $query->whereDate('created_at', '<=', $request->input('date_end'));
            }

            $entries = $query->orderBy('created_at', 'desc')->get();
            $totalCents = $entries->sum('amount_cents');

            return response()->json([
                'total_cents' => $totalCents,
                'entries' => $entries,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }
}
