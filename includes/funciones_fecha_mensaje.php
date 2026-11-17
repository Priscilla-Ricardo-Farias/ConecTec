<?php
// includes/funciones_fecha_mensaje.php
//
// Detecta si un mensaje del chat habla de una fecha (y una hora, si la
// dice) para ofrecer "Agregar al calendario" al mantener apretado el mensaje.
// Entiende cómo se escribe en Argentina (día/mes/año):
//
//   "nos vemos el 25/10"            "el 3 de noviembre a las 15:30"
//   "mañana a las 9"                "pasado mañana"
//   "el viernes a las 18 hs"        "hoy 14:00"
//
// Las fechas relativas ("mañana", "el viernes") se cuentan desde el día en
// que se mandó el mensaje, no desde hoy.

function texto_plano_minusculas($texto) {
	$texto = mb_strtolower((string) $texto);
	return strtr($texto, [
		'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
	]);
}

/**
 * Devuelve ['fecha' => 'Y-m-d', 'hora' => 'H:i' o null] si el mensaje menciona una
 * fecha, o null si no. $momento_mensaje es la fecha y hora en que se mandó (para "mañana", "el lunes"...).
 */
function detectar_fecha_en_texto($texto, $momento_mensaje = null) {
	$t = texto_plano_minusculas($texto);

	if (trim($t) === '') {
		return null;
	}

	try {
		$base = new DateTime($momento_mensaje ?: 'now');
	} catch (Exception $e) {
		$base = new DateTime('now');
	}
	$base->setTime(0, 0, 0);

	$meses = [
		'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
		'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
		'noviembre' => 11, 'diciembre' => 12,
	];
	$dias_semana = [
		'domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3,
		'jueves' => 4, 'viernes' => 5, 'sabado' => 6,
	];

	$fecha = null;
	$resto = $t;   // el texto sin la fecha encontrada, para buscar la hora sin confundirla

	// 1) 25/10/2026, 25-10-26, 25.10.2026
	if (preg_match('~(?<![\d/])(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4}|\d{2})(?![\d/])~', $t, $m)) {
		$anio = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
		if (checkdate((int) $m[2], (int) $m[1], $anio)) {
			$fecha = sprintf('%04d-%02d-%02d', $anio, $m[2], $m[1]);
			$resto = str_replace($m[0], ' ', $t);
		}
	}

	// 2) 3 de noviembre, 3 de noviembre de 2026
	if ($fecha === null && preg_match('~(?<!\d)(\d{1,2})\s+de\s+(' . implode('|', array_keys($meses)) . ')(?:\s+(?:de|del)\s+(\d{4}))?~', $t, $m)) {
		$mes  = $meses[$m[2]];
		$dia  = (int) $m[1];
		$anio = !empty($m[3]) ? (int) $m[3] : (int) $base->format('Y');

		if (checkdate($mes, $dia, $anio)) {
			$candidata = new DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
			// Sin año y ya pasó este año: se asume el año que viene.
			if (empty($m[3]) && $candidata < $base) {
				$candidata->modify('+1 year');
			}
			$fecha = $candidata->format('Y-m-d');
			$resto = str_replace($m[0], ' ', $t);
		}
	}

	// 3) "25/10" (sin año)
	if ($fecha === null && preg_match('~(?<![\d/])(\d{1,2})/(\d{1,2})(?![\d/])(?!\s+de\s)~', $t, $m)) {
		// (el "(?!\s+de\s)" evita tomar como fecha cosas como "3/4 de pared")
		$dia = (int) $m[1];
		$mes = (int) $m[2];
		$anio = (int) $base->format('Y');

		if (checkdate($mes, $dia, $anio)) {
			$candidata = new DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
			if ($candidata < $base) {
				$candidata->modify('+1 year');
			}
			$fecha = $candidata->format('Y-m-d');
			$resto = str_replace($m[0], ' ', $t);
		}
	}

	// 4) pasado mañana / mañana / hoy ("por la mañana" no cuenta: es la parte del día)
	if ($fecha === null) {
		if (preg_match('~\bpasado\s+manana\b~', $t, $m)) {
			$fecha = (clone $base)->modify('+2 days')->format('Y-m-d');
			$resto = str_replace($m[0], ' ', $t);
		} elseif (preg_match('~(?<!la\s)(?<!esta\s)\bmanana\b~', $t, $m)) {
			$fecha = (clone $base)->modify('+1 day')->format('Y-m-d');
			$resto = str_replace($m[0], ' ', $t);
		} elseif (preg_match('~\bhoy\b~', $t, $m)) {
			$fecha = $base->format('Y-m-d');
			$resto = str_replace($m[0], ' ', $t);
		}
	}

	// 5) el lunes, este viernes, el próximo martes: el primero que viene (nunca el mismo día)
	if ($fecha === null && preg_match('~\b(' . implode('|', array_keys($dias_semana)) . ')\b~', $t, $m)) {
		$objetivo = $dias_semana[$m[1]];
		$hoy      = (int) $base->format('w');
		$faltan   = ($objetivo - $hoy + 7) % 7;
		$faltan   = $faltan === 0 ? 7 : $faltan;
		$fecha    = (clone $base)->modify('+' . $faltan . ' days')->format('Y-m-d');
		$resto    = str_replace($m[0], ' ', $t);
	}

	if ($fecha === null) {
		return null;
	}

	return ['fecha' => $fecha, 'hora' => detectar_hora_en_texto($resto)];
}

/** "a las 15:30", "18 hs", "a las 3 de la tarde", "9am" -> 'H:i' (o null si no dice hora). */
function detectar_hora_en_texto($t) {
	$hora = null;
	$min  = 0;

	if (preg_match('~(?<![\d/])(\d{1,2})[:.h](\d{2})\s*(?:hs|h|horas|am|pm)?(?!\d)~', $t, $m)) {
		$hora = (int) $m[1];
		$min  = (int) $m[2];
	} elseif (preg_match('~(?<![\d/])(\d{1,2})\s*(?:hs|horas|h|am|pm)\b~', $t, $m)) {
		$hora = (int) $m[1];
	} elseif (preg_match('~\ba\s+las?\s+(\d{1,2})\b(?!\s*[/\-])~', $t, $m)) {
		$hora = (int) $m[1];
	}

	if ($hora === null || $hora > 23 || $min > 59) {
		return null;
	}

	// "a las 3 de la tarde / de la noche" o "pm": pasa a formato de 24 horas
	if ($hora < 12 && preg_match('~(?:de\s+la\s+(?:tarde|noche)|\bpm\b|\d\s*pm)~', $t)) {
		$hora += 12;
	}

	return sprintf('%02d:%02d', $hora, $min);
}
