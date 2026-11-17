<?php
// includes/idioma.php
//
// Helper mínimo de traducciones. El idioma activo se guarda en
// $_SESSION['usuario_idioma'] (se carga en procesar_login.php y se
// actualiza en procesar_ajustes.php / ajustes.php).
//
// Por ahora el diccionario cubre lo que se ve en ajustes.php. Para
// traducir otra página, agregá sus claves acá y usá t('clave') en
// vez del texto fijo.

$GLOBALS['_textos'] = [
	'es' => [
		'volver'                     => 'Volver',
		'nav_cuenta'                  => 'Cuenta',
		'nav_notificaciones'          => 'Notificaciones',
		'nav_preferencias_busqueda'   => 'Preferencias de búsqueda',
		'nav_preferencias_contrato'   => 'Preferencias de contratación',
		'nav_privacidad'              => 'Privacidad y seguridad',
		'nav_apariencia'              => 'Apariencia',
		'nav_ayuda'                   => 'Ayuda',
		'sec_cuenta'                  => 'Cuenta',
		'sec_notificaciones'          => 'Notificaciones',
		'sec_privacidad'              => 'Privacidad y seguridad',
		'sec_apariencia'              => 'Apariencia',
		'sec_ayuda'                   => 'Ayuda',
		'guardar_cambios'             => 'Guardar cambios',
	],
	'en' => [
		'volver'                     => 'Back',
		'nav_cuenta'                  => 'Account',
		'nav_notificaciones'          => 'Notifications',
		'nav_preferencias_busqueda'   => 'Search preferences',
		'nav_preferencias_contrato'   => 'Hiring preferences',
		'nav_privacidad'              => 'Privacy & security',
		'nav_apariencia'              => 'Appearance',
		'nav_ayuda'                   => 'Help',
		'sec_cuenta'                  => 'Account',
		'sec_notificaciones'          => 'Notifications',
		'sec_privacidad'              => 'Privacy & security',
		'sec_apariencia'              => 'Appearance',
		'sec_ayuda'                   => 'Help',
		'guardar_cambios'             => 'Save changes',
	],
	'pt' => [
		'volver'                     => 'Voltar',
		'nav_cuenta'                  => 'Conta',
		'nav_notificaciones'          => 'Notificações',
		'nav_preferencias_busqueda'   => 'Preferências de busca',
		'nav_preferencias_contrato'   => 'Preferências de contratação',
		'nav_privacidad'              => 'Privacidade e segurança',
		'nav_apariencia'              => 'Aparência',
		'nav_ayuda'                   => 'Ajuda',
		'sec_cuenta'                  => 'Conta',
		'sec_notificaciones'          => 'Notificações',
		'sec_privacidad'              => 'Privacidade e segurança',
		'sec_apariencia'              => 'Aparência',
		'sec_ayuda'                   => 'Ajuda',
		'guardar_cambios'             => 'Salvar alterações',
	],
];

/**
 * Devuelve el texto traducido para $clave, según el idioma activo en
 * la sesión ($_SESSION['usuario_idioma']). Si la clave no existe en
 * ese idioma, cae a español; si no existe en ningún lado, devuelve la
 * clave tal cual (para notar fácil que falta cargarla).
 */
function t($clave) {
	$idioma = $_SESSION['usuario_idioma'] ?? 'es';
	return $GLOBALS['_textos'][$idioma][$clave]
		?? $GLOBALS['_textos']['es'][$clave]
		?? $clave;
}