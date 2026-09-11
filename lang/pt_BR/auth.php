<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */
    'welcome' => '👋 Bem-vindo(a), :name!',
    'welcome-back' => '👋 Bem-vindo(a) de volta, :name!',
    'failed' => 'Estas credenciais não correspondem aos nossos registros.',
    'password' => 'A senha fornecida está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',
    'verification-link-sent' => 'Um novo link de verificação foi enviado para seu endereço de email.',
    'registration' => [
        'title' => 'Cadastro',
        'description' => 'Controle se visitantes podem criar novas contas.',
        'enabled' => 'Permitir novos cadastros',
        'help' => 'Quando desativado, a página de cadastro retorna 404 e o login social não cria novas contas.',
    ],
    'modal' => [
        'enabled' => 'Abrir login e cadastro em um modal',
        'help' => 'Os links do site abrem o login e o cadastro sobre a página atual. As URLs continuam funcionando sozinhas, então um link digitado, salvo ou compartilhado leva à página completa.',
    ],
    'notifications' => [
        'title' => 'Notificações',
        'description' => 'Configure as notificações de segurança enviadas aos usuários.',
        'login-enabled' => 'Enviar notificações de acesso',
        'login-help' => 'Envie um email aos usuários após um acesso bem-sucedido à conta.',
    ],
    'login-notification' => [
        'subject' => 'Novo acesso à sua conta :app',
        'greeting' => 'Olá :name,',
        'notice' => 'Notamos um novo acesso à sua conta :app.',
        'app' => 'Aplicativo: :app',
        'time' => 'Horário: :time',
        'ip-address' => 'Endereço IP: :ip',
        'device-details' => 'Detalhes do dispositivo: :device',
        'recognized' => 'Se foi você, nenhuma ação é necessária.',
        'action' => 'Redefinir sua senha',
        'unrecognized' => 'Se você não reconhece esta atividade, redefina sua senha imediatamente.',
        'unknown' => 'Desconhecido',
    ],
];
