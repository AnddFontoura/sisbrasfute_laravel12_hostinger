<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\PlayerSelfAssignRequest;
use App\Service\MatchPositionService;
use Illuminate\Http\JsonResponse;

class PlayerSelfAssignController extends Controller
{
    public function __construct(
        protected MatchPositionService $matchPositionService,
    ) {

    }

    public function store(PlayerSelfAssignRequest $request, int $matchId): JsonResponse
    {
        try {
            $userId = auth()->id();

            $method = PaymentMethod::from($request->input('payment_method', 'wallet'));
            $returnUrl = $request->input('return_url', config('app.frontend_url') . '/matches/' . $matchId . '/choose-position');

            $payer = [
                'name' => $request->input('payer_name'),
                'document' => $request->input('payer_document'),
                'cep' => $request->input('payer_cep'),
                'address' => $request->input('payer_address'),
                'city' => $request->input('payer_city'),
                'uf' => $request->input('payer_uf'),
            ];

            $result = $this->matchPositionService->selfAssignPosition(
                $matchId,
                $request->game_position_id,
                $userId,
                $method,
                $returnUrl,
                $payer,
            );

            $charge = $result['charge'];

            return response()->json([
                'assignment' => $result['assignment'],
                'payment_status' => $result['assignment']->payment_status,
                'payment_method' => $method->value,
                'charge' => $charge ? [
                    'method' => $charge->method,
                    'status' => $charge->status,
                    'pix_copia_e_cola' => $charge->pixCopiaECola,
                    'pix_qrcode_base64' => $charge->pixQrCodeBase64,
                    'boleto_linha_digitavel' => $charge->boletoLinhaDigitavel,
                    'boleto_pdf_url' => $charge->boletoPdfUrl,
                    'boleto_due_date' => $charge->boletoDueDate,
                ] : null,
            ], JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }

    public function destroy(int $matchId): JsonResponse
    {
        try {
            $userId = auth()->id();

            $this->matchPositionService->releasePosition($matchId, $userId);

            return response()->json(
                ['success' => 'Posição liberada com sucesso'],
                JsonResponse::HTTP_OK
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                $this->normalizeStatusCode($e->getCode())
            );
        }
    }
}
