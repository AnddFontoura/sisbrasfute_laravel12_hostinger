<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error Language Lines (Português do Brasil)
    |--------------------------------------------------------------------------
    |
    | Mensagens retornadas pela API em respostas de erro. As chaves são
    | agrupadas por domínio (player, team, ...). Ao adicionar uma nova chave,
    | inclua-a em todos os idiomas suportados para manter o sistema bilíngue.
    |
    */

    'common' => [
        'resource_not_found' => 'Recurso não encontrado.',
    ],

    'auth' => [
        'forbidden' => 'Você não tem permissão para acessar essa página.',
        'unauthorized' => 'Não autorizado.',
    ],

    'player' => [
        'player_profile_not_found' => 'Perfil de jogador não encontrado.',
    ],

    'team' => [
        'team_not_found' => 'Time não encontrado.',
        'player_already_applied' => 'Você já se candidatou a este time.',
        'logo_not_found' => 'Imagem não encontrada para este time.',
    ],

    'game_position' => [
        'not_found' => 'Posição não encontrada.',
    ],

    'preset' => [
        'not_found' => 'Preset não encontrado.',
    ],

    'user' => [
        'not_found' => 'Usuário não encontrado.',
    ],

    'notification' => [
        'not_found' => 'Notificação não encontrada.',
        'no_recipients' => 'Nenhum destinatário encontrado para o público selecionado.',
    ],

    'finance' => [
        'record_not_found' => 'Registro não encontrado',
    ],

    'match' => [
        'not_found' => 'Partida não encontrada.',
        'cannot_edit' => 'Você não tem permissão para editar esta partida.',
        'cannot_deactivate' => 'Você não tem permissão para desativar esta partida.',
        'cannot_reactivate' => 'Você não tem permissão para reativar esta partida.',
        'stats_forbidden' => 'Você não tem permissão para gerenciar estatísticas desta partida.',
    ],

    'challenge' => [
        'not_found' => 'Desafio não encontrado.',
        'forbidden' => 'Sem permissão.',
        'not_open' => 'Esta partida não está aberta para desafios.',
        'not_team_admin' => 'Você não administra este time.',
        'cannot_challenge_own' => 'Você não pode desafiar sua própria partida.',
        'already_sent' => 'Este time já enviou um desafio para esta partida.',
        'not_pending' => 'Este desafio não está mais pendente.',
        'cannot_decline_confirmed' => 'Não é possível recusar um desafio já confirmado.',
        'cannot_cancel_confirmed' => 'Não é possível cancelar um desafio já confirmado.',
        'host_not_accepted' => 'O anfitrião ainda não aceitou este desafio.',
    ],

    'email_verification' => [
        'link_expired' => 'O link de verificação expirou. Solicite um novo.',
        'invalid_link' => 'Link de verificação inválido.',
        'throttled' => 'Aguarde 60 segundos antes de solicitar outro email.',
    ],

    'password_reset' => [
        'invalid_token' => 'Token inválido ou expirado.',
    ],

];
