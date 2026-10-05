<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ConfigurationController extends Controller
{
    /**
     * Retorna dados do perfil do usuário autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'cpf' => $user->cpf,
            'rg' => $user->rg,
        ], Response::HTTP_OK);
    }

    /**
     * Atualiza dados pessoais (nome, CPF, RG).
     * O email não pode ser alterado por esta rota.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cpf' => ['nullable', 'string', 'max:14', 'unique:users,cpf,' . $user->id, 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
            'rg' => ['nullable', 'string', 'max:20'],
        ], [
            'cpf.regex' => __('validation.custom.cpf.regex'),
            'cpf.unique' => __('validation.custom.cpf.unique'),
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['errors' => $validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Validação de dígitos verificadores do CPF
        if ($request->cpf && !$this->isValidCpf($request->cpf)) {
            return response()->json(
                ['errors' => ['cpf' => [__('validation.custom.cpf.invalid')]]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user->update([
            'name' => $request->name,
            'cpf' => $request->cpf,
            'rg' => $request->rg,
        ]);

        return response()->json([
            'message' => __('messages.account.updated'),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'rg' => $user->rg,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Atualiza senha do usuário.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => __('validation.custom.current_password.required'),
            'new_password.required' => __('validation.custom.new_password.required'),
            'new_password.min' => __('validation.custom.new_password.min'),
            'new_password.confirmed' => __('validation.custom.new_password.confirmed'),
        ]);

        if ($validator->fails()) {
            return response()->json(
                ['errors' => $validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(
                ['errors' => ['current_password' => [__('validation.custom.current_password.incorrect')]]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => __('messages.account.password_updated'),
        ], Response::HTTP_OK);
    }

    /**
     * Valida CPF com dígitos verificadores (algoritmo da Receita Federal).
     */
    private function isValidCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11) return false;

        // Elimina CPFs com todos os dígitos iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;

        // Cálculo do primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;

        if ((int) $cpf[9] !== $digit1) return false;

        // Cálculo do segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $cpf[10] === $digit2;
    }
}
