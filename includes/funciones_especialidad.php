<?php
// includes/funciones_especialidad.php
//
// Especialidades de Conectec: la lista completa de especialidades
// técnicas, las variaciones con que la gente las escribe y las
// comparaciones entre lo que sabe hacer un técnico y lo que pide un
// trabajo (trabajo.categoria).
//
// Cómo se guardan las cosas:
//   - tecnico.especialidad      → la especialidad PRINCIPAL del técnico
//                                  (se mantiene para el panel de admin y
//                                  para cuentas viejas).
//   - tecnico_especialidad      → TODAS las especialidades del técnico, con
//                                  su "clave" ya normalizada (ver más abajo).
//   - trabajo.categoria         → la clave de la lista (ej: "informatica")
//                                  o, si eligieron "Otro", lo que escribieron.
//
// La "clave" de una especialidad es una palabra en minúsculas y sin tildes
// ("informatica"). clave_especialidad() convierte cualquier forma de
// escribirla ("Informático", "informatica/o", "Técnico/a en Informática",
// "INFORMATICA", "informatca"...) en esa misma clave, así todo se compara
// igual sin importar cómo lo haya escrito cada persona.

/**
 * Catálogo completo: clave => ['etiqueta' => texto a mostrar, 'alias' => [otras formas de escribirla]].
 * Para agregar una especialidad nueva alcanza con sumar una línea acá: aparece sola
 * en todos los formularios, filtros y buscadores.
 */
function catalogo_especialidades() {
	static $catalogo = null;

	if ($catalogo !== null) {
		return $catalogo;
	}

	$catalogo = [
		// --- las que ya existían (se mantienen las mismas claves) ---
		'electricista'   => ['etiqueta' => 'Electricista', 'alias' => ['electricidad', 'electrico', 'electrica', 'instalador electrico', 'instalaciones electricas', 'electricista matriculado']],
		'mecanico'       => ['etiqueta' => 'Mecánico', 'alias' => ['mecanica', 'mecanica general', 'mecanica industrial', 'mecanico de taller']],
		'electronico'    => ['etiqueta' => 'Electrónico', 'alias' => ['electronica', 'electronica general']],
		'soldador'       => ['etiqueta' => 'Soldador', 'alias' => ['soldadura', 'soldadura industrial', 'soldadura electrica']],
		'automatizacion' => ['etiqueta' => 'Automatización', 'alias' => ['automatizacion industrial', 'automatizado', 'automatas', 'plc', 'automatizacion y control']],

		// --- el resto de las especialidades técnicas ---
		'informatica'    => ['etiqueta' => 'Informática', 'alias' => ['informatico', 'computacion', 'computadoras', 'computadora', 'pc', 'reparacion de pc', 'sistemas', 'soporte tecnico', 'soporte it', 'it', 'hardware', 'tecnico en computadoras', 'ciencias de la computacion']],
		'programacion'   => ['etiqueta' => 'Programación', 'alias' => ['programador', 'desarrollo de software', 'desarrollo web', 'software', 'desarrollador', 'desarrollador web', 'analista programador', 'sistemas de informacion']],
		'redes'          => ['etiqueta' => 'Redes y telecomunicaciones', 'alias' => ['telecomunicaciones', 'telecomunicacion', 'redes informaticas', 'redes de datos', 'cableado estructurado', 'networking', 'redes y telecomunicacion', 'comunicaciones']],
		'electromecanico' => ['etiqueta' => 'Electromecánico', 'alias' => ['electromecanica', 'electro mecanico', 'electro mecanica', 'equipos e instalaciones electromecanicas', 'instalaciones electromecanicas']],
		'mecatronica'    => ['etiqueta' => 'Mecatrónica', 'alias' => ['mecatronico']],
		'robotica'       => ['etiqueta' => 'Robótica', 'alias' => ['robotico', 'robots', 'robot']],
		'instrumentacion' => ['etiqueta' => 'Instrumentación y control', 'alias' => ['instrumentista', 'control industrial', 'sistemas de control', 'instrumentacion industrial', 'control y automatizacion']],
		'automotor'      => ['etiqueta' => 'Automotor', 'alias' => ['automotores', 'automotriz', 'mecanico automotor', 'mecanico del automotor', 'mecanica automotriz', 'mecanico de autos', 'mecanica del automotor', 'autos', 'auto']],
		'aeronautico'    => ['etiqueta' => 'Aeronáutica', 'alias' => ['aeronautica', 'aviones', 'mecanico de aviones', 'aeronaves', 'aviacion']],
		'refrigeracion'  => ['etiqueta' => 'Refrigeración y climatización', 'alias' => ['climatizacion', 'aire acondicionado', 'aires acondicionados', 'frigorista', 'refrigeracion y aire acondicionado', 'frio y calor', 'refrigeracion y climatizacion', 'camaras frigorificas']],
		'gasista'        => ['etiqueta' => 'Gasista', 'alias' => ['gas', 'instalador de gas', 'gasista matriculado', 'instalaciones de gas']],
		'plomero'        => ['etiqueta' => 'Plomería', 'alias' => ['plomeria', 'sanitarista', 'sanitarios', 'instalaciones sanitarias', 'agua y cloacas']],
		'construcciones' => ['etiqueta' => 'Construcciones', 'alias' => ['construccion', 'constructor', 'maestro mayor de obras', 'maestro de obra', 'mmo', 'albanil', 'albanileria', 'obra civil', 'obras civiles']],
		'carpintero'     => ['etiqueta' => 'Carpintería', 'alias' => ['carpinteria', 'madera', 'muebles', 'ebanista', 'ebanisteria', 'industria de la madera']],
		'metalurgico'    => ['etiqueta' => 'Metalúrgico', 'alias' => ['metalurgica', 'metalurgia', 'herrero', 'herreria', 'chapista', 'chapa y pintura', 'hojalatero', 'estructuras metalicas']],
		'tornero'        => ['etiqueta' => 'Tornero / Matricero', 'alias' => ['torneria', 'matricero', 'matriceria', 'fresador', 'fresadora', 'mecanizado', 'cnc', 'operador cnc', 'maquinas herramientas', 'maquinista']],
		'cerrajero'      => ['etiqueta' => 'Cerrajero', 'alias' => ['cerrajeria', 'cerraduras', 'cerrajero matriculado']],
		'quimico'        => ['etiqueta' => 'Químico', 'alias' => ['quimica', 'quimica industrial', 'quimico industrial', 'laboratorio', 'analista quimico', 'tecnico quimico']],
		'procesos'       => ['etiqueta' => 'Industria de procesos', 'alias' => ['industrias de procesos', 'procesos industriales', 'industria de proceso', 'procesos']],
		'alimentos'      => ['etiqueta' => 'Industria de la alimentación', 'alias' => ['alimentacion', 'industrias de la alimentacion', 'industria alimenticia', 'tecnologia de los alimentos', 'alimenticia']],
		'agropecuario'   => ['etiqueta' => 'Agropecuario', 'alias' => ['agrotecnico', 'agrotecnica', 'agronomia', 'produccion agropecuaria', 'agro', 'agricola', 'ganaderia']],
		'energias'       => ['etiqueta' => 'Energías renovables', 'alias' => ['energia solar', 'energia renovable', 'energias renovables', 'paneles solares', 'solar', 'fotovoltaica', 'eolica', 'instalador solar', 'energia']],
		'petroleo'       => ['etiqueta' => 'Petróleo y minería', 'alias' => ['petroleo y gas', 'mineria', 'minero', 'petroquimica', 'hidrocarburos']],
		'seguridad'      => ['etiqueta' => 'Seguridad e higiene', 'alias' => ['higiene y seguridad', 'seguridad e higiene laboral', 'seguridad industrial', 'seguridad laboral', 'higiene industrial']],
		'alarmas'        => ['etiqueta' => 'Alarmas y cámaras de seguridad', 'alias' => ['alarma', 'camaras de seguridad', 'camaras', 'cctv', 'seguridad electronica', 'domotica', 'videovigilancia', 'camaras de vigilancia']],
		'electrodomesticos' => ['etiqueta' => 'Electrodomésticos', 'alias' => ['electrodomestico', 'reparacion de electrodomesticos', 'service de electrodomesticos', 'lavarropas', 'heladeras', 'linea blanca']],
		'celulares'      => ['etiqueta' => 'Celulares', 'alias' => ['celular', 'telefonia', 'telefonia celular', 'reparacion de celulares', 'tecnico de celulares', 'telefonos', 'smartphones', 'moviles']],
		'biomedico'      => ['etiqueta' => 'Equipos biomédicos', 'alias' => ['biomedica', 'electromedicina', 'equipos medicos', 'tecnico biomedico', 'equipamiento medico']],
		'mantenimiento'  => ['etiqueta' => 'Mantenimiento industrial', 'alias' => ['mantenimiento', 'mantenimiento general', 'mantenimiento de edificios', 'mantenimiento edilicio', 'mantenimiento electromecanico']],
		'diseno'         => ['etiqueta' => 'Diseño industrial', 'alias' => ['diseno', 'diseno de producto', 'diseno de productos', 'dibujo tecnico', 'cad', 'diseno mecanico']],
		'multimedia'     => ['etiqueta' => 'Multimedia y audiovisual', 'alias' => ['multimedial', 'diseno multimedial', 'comunicacion multimedial', 'diseno y comunicacion multimedial', 'audiovisual', 'medios audiovisuales', 'diseno grafico', 'edicion de video', 'sonido', 'iluminacion']],
		'textil'         => ['etiqueta' => 'Textil e indumentaria', 'alias' => ['indumentaria', 'costura industrial', 'confeccion', 'industria textil']],
	];

	return $catalogo;
}

/** Lista fija de especialidades: clave => texto a mostrar, ordenada A-Z (es lo que usan los desplegables). */
function especialidades_conectec() {
	static $lista = null;

	if ($lista !== null) {
		return $lista;
	}

	$lista = [];
	foreach (catalogo_especialidades() as $clave => $dato) {
		$lista[$clave] = $dato['etiqueta'];
	}

	// Orden alfabético ignorando tildes ("Electrónico" va junto a "Electricista", no al final).
	uasort($lista, function ($a, $b) {
		return strcmp(normalizar_especialidad($a), normalizar_especialidad($b));
	});

	return $lista;
}

/** Minúsculas, sin tildes ni signos, con un solo espacio entre palabras: " Mecánico/a " -> "mecanico a". */
function normalizar_especialidad($texto) {
	$texto = mb_strtolower(trim((string) $texto));
	$texto = strtr($texto, [
		'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
		'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
		'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
		'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
		'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
		'ñ' => 'n',
	]);
	$texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
	return trim($texto);
}

/**
 * "Raíz" aproximada de una frase para juntar las variaciones de género y número:
 * informatica / informatico / informaticos -> "informatic". Las palabras cortas no se tocan.
 */
function raiz_especialidad($normalizado) {
	$palabras = explode(' ', $normalizado);
	foreach ($palabras as $i => $palabra) {
		if (strlen($palabra) > 4) {
			$palabras[$i] = preg_replace('/(?:[aeo]s?|es)$/', '', $palabra);
		}
	}
	return implode(' ', $palabras);
}

/** Índices para buscar rápido: texto normalizado => clave, y raíz => clave. Se arma una sola vez. */
function indice_especialidades() {
	static $indice = null;

	if ($indice !== null) {
		return $indice;
	}

	$indice = ['exacto' => [], 'raiz' => []];

	foreach (catalogo_especialidades() as $clave => $dato) {
		$textos = array_merge([$clave, $dato['etiqueta']], $dato['alias']);
		foreach ($textos as $texto) {
			$n = normalizar_especialidad($texto);
			if ($n === '') {
				continue;
			}
			if (!isset($indice['exacto'][$n])) {
				$indice['exacto'][$n] = $clave;
			}
			$r = raiz_especialidad($n);
			if (!isset($indice['raiz'][$r])) {
				$indice['raiz'][$r] = $clave;
			}
		}
	}

	return $indice;
}

/**
 * Convierte cualquier forma de escribir una especialidad en su clave.
 *
 *   "Informática", "INFORMATICO", "informatica/o", "Técnico/a en Informática",
 *   "informatca" (con un error de tipeo)  ->  "informatica"
 *
 * Si no se parece a ninguna de la lista (por ejemplo un "Otro" escrito a mano),
 * devuelve el texto normalizado, así dos personas que escriban lo mismo
 * igual coinciden. Devuelve '' si no hay nada escrito.
 */
function clave_especialidad($texto) {
	$t = (string) $texto;

	// "informatica/o", "tecnico/a", "electricista(a)": se saca la terminación de género.
	$t = preg_replace('~\s*[/\\\\]\s*(?:o|a|e|os|as|es)(?![\p{L}\p{N}])~iu', '', $t);
	$t = preg_replace('~\s*\(\s*(?:o|a|e|os|as|es|a/o|o/a)\s*\)~iu', '', $t);

	$n = normalizar_especialidad($t);

	if ($n === '') {
		return '';
	}

	// "tecnico en X", "tecnica de X", "tec X", "especialista en X" -> "X"
	$sin_prefijo = preg_replace('/^(?:tecnic[oa]s?|tec|especialista|profesional|ingenier[oa]|ing)\s+(?:(?:en|de|del|para)\s+)?/', '', $n);
	$sin_prefijo = trim($sin_prefijo);

	$candidatos = $sin_prefijo !== '' && $sin_prefijo !== $n ? [$sin_prefijo, $n] : [$n];
	$indice     = indice_especialidades();

	// 1) coincidencia exacta con la clave, la etiqueta o algún alias
	foreach ($candidatos as $c) {
		if (isset($indice['exacto'][$c])) {
			return $indice['exacto'][$c];
		}
	}

	// 2) misma raíz (género / plural distinto)
	foreach ($candidatos as $c) {
		$r = raiz_especialidad($c);
		if (isset($indice['raiz'][$r])) {
			return $indice['raiz'][$r];
		}
	}

	// 3) un error de tipeo (una letra de más, de menos o cambiada)
	foreach ($candidatos as $c) {
		$r = raiz_especialidad($c);
		if (strlen($r) >= 6) {
			foreach ($indice['raiz'] as $raiz_catalogo => $clave) {
				if (abs(strlen($raiz_catalogo) - strlen($r)) <= 1 && levenshtein($r, $raiz_catalogo) <= 1) {
					return $clave;
				}
			}
		}
	}

	// No está en la lista: se compara por el texto normalizado.
	return $candidatos[0];
}

/** ¿Esta clave es una especialidad de la lista (y no un "Otro" escrito a mano)? */
function es_especialidad_de_lista($clave) {
	return isset(catalogo_especialidades()[$clave]);
}

/** Texto para mostrar: "informatico" / "INFORMATICA" -> "Informática"; un "Otro" escrito a mano se deja como está. */
function etiqueta_especialidad($valor) {
	$valor = trim((string) $valor);

	if ($valor === '') {
		return '';
	}

	$clave    = clave_especialidad($valor);
	$catalogo = catalogo_especialidades();

	if (isset($catalogo[$clave])) {
		return $catalogo[$clave]['etiqueta'];
	}

	return mb_strtoupper(mb_substr($valor, 0, 1)) . mb_substr($valor, 1);
}

/** true si la categoría del trabajo coincide con la especialidad (da igual cómo esté escrita cada una). */
function misma_especialidad($especialidad, $categoria) {
	$a = clave_especialidad($especialidad);
	return $a !== '' && $a === clave_especialidad($categoria);
}

/**
 * Filtro por especialidad. $filtro es '' (todas), una clave de
 * especialidades_conectec() u 'otro' (todo lo que no está en la lista fija).
 */
function categoria_coincide_filtro($categoria, $filtro) {
	if ($filtro === '') {
		return true;
	}
	$cat = clave_especialidad($categoria);
	if ($filtro === 'otro') {
		return $cat !== '' && !es_especialidad_de_lista($cat);
	}
	return $cat === $filtro;
}

/**
 * Lo que se guarda en trabajo.categoria: la clave si es de la lista
 * (sin importar cómo la hayan escrito), o el texto tal cual si es un "Otro".
 */
function categoria_para_guardar($texto) {
	$texto = trim((string) $texto);
	$clave = clave_especialidad($texto);

	if (es_especialidad_de_lista($clave)) {
		return $clave;
	}

	return mb_substr($texto, 0, 100);
}

/**
 * <option> de todas las especialidades de la lista (A-Z). Lo usan registro, cambiar a
 * técnico, publicar, ajustes y los filtros, para que todos muestren la misma lista.
 * $con_otro agrega al final la opción "Otro" (value="otro").
 */
function opciones_especialidades_html($seleccionada = '', $con_otro = true) {
	$html = '';
	$sel  = clave_especialidad($seleccionada);

	foreach (especialidades_conectec() as $clave => $etiqueta) {
		$marca = ($sel !== '' && $sel === $clave) ? ' selected' : '';
		$html .= '<option value="' . htmlspecialchars($clave) . '"' . $marca . '>' . htmlspecialchars($etiqueta) . '</option>' . "\n";
	}

	if ($con_otro) {
		$html .= '<option value="otro">Otro</option>' . "\n";
	}

	return $html;
}

/* =========================================================
   ESPECIALIDADES DE UN TÉCNICO (varias por técnico)
   ========================================================= */

/**
 * Todas las especialidades de un técnico, la principal primero:
 * [['especialidad' => 'Informática', 'clave' => 'informatica'], ...]
 * Si todavía no existe la tabla tecnico_especialidad (no se corrió la
 * migración) o el técnico no tiene filas, se usa tecnico.especialidad.
 */
function especialidades_de_tecnico($conexion, $id_tecnico) {
	$id    = (int) $id_tecnico;
	$lista = [];

	try {
		$stmt = $conexion->prepare('SELECT especialidad, clave FROM tecnico_especialidad WHERE id_tecnico = ? ORDER BY id_tecnico_especialidad');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$lista = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();

		// Autoarreglo: si la clave guardada no es la que corresponde (por ejemplo, filas que
		// creó la migración con un texto viejo como "Técnico en electricidad"), se corrige acá.
		foreach ($lista as $i => $fila) {
			$clave_correcta = clave_especialidad($fila['especialidad']);
			if ($clave_correcta !== '' && $clave_correcta !== $fila['clave']) {
				try {
					$arreglo = $conexion->prepare('UPDATE IGNORE tecnico_especialidad SET clave = ? WHERE id_tecnico = ? AND clave = ?');
					$arreglo->bind_param('sis', $clave_correcta, $id, $fila['clave']);
					$arreglo->execute();
					$arreglo->close();
					$lista[$i]['clave'] = $clave_correcta;
				} catch (Throwable $e) {
					// si no se puede corregir, se usa igual la clave correcta en esta consulta
					$lista[$i]['clave'] = $clave_correcta;
				}
			}
		}
	} catch (Throwable $e) {
		$lista = [];
	}

	if (empty($lista)) {
		$stmt = $conexion->prepare('SELECT especialidad FROM tecnico WHERE id_tecnico = ?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();

		if ($fila && trim((string) $fila['especialidad']) !== '') {
			$lista[] = [
				'especialidad' => etiqueta_especialidad($fila['especialidad']),
				'clave'        => clave_especialidad($fila['especialidad']),
			];
		}
	}

	return $lista;
}

/** ¿El técnico tiene alguna especialidad que coincida con la categoría de ese trabajo? */
function tecnico_tiene_especialidad($conexion, $id_tecnico, $categoria) {
	$clave = clave_especialidad($categoria);

	if ($clave === '') {
		return false;
	}

	foreach (especialidades_de_tecnico($conexion, $id_tecnico) as $esp) {
		if ($esp['clave'] === $clave) {
			return true;
		}
	}

	return false;
}

/**
 * Deja al técnico con EXACTAMENTE estas especialidades (valores de la lista o texto
 * escrito a mano; las repetidas y las variaciones de la misma se juntan en una sola).
 * La especialidad principal (tecnico.especialidad) se mantiene si sigue elegida;
 * si no, pasa a ser la primera. Devuelve false si no quedó ninguna especialidad válida.
 *
 * $propia_transaccion = false cuando quien llama ya abrió una transacción
 * (por ejemplo el registro), para no cerrarla a la mitad.
 */
function guardar_especialidades_tecnico($conexion, $id_tecnico, array $valores, $propia_transaccion = true) {
	$id       = (int) $id_tecnico;
	$catalogo = catalogo_especialidades();
	$items    = []; // clave => texto que se guarda

	foreach ($valores as $valor) {
		$valor = trim((string) $valor);

		if ($valor === '' || normalizar_especialidad($valor) === 'otro') {
			continue;
		}

		$clave = clave_especialidad($valor);

		if ($clave === '' || isset($items[$clave])) {
			continue;
		}

		$items[$clave] = isset($catalogo[$clave]) ? $catalogo[$clave]['etiqueta'] : mb_substr($valor, 0, 100);

		if (count($items) >= 15) {
			break;
		}
	}

	if (empty($items)) {
		return false;
	}

	// La principal actual pasa primero si el técnico la sigue teniendo.
	$stmt = $conexion->prepare('SELECT especialidad FROM tecnico WHERE id_tecnico = ?');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	$actual = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($actual) {
		$clave_principal = clave_especialidad($actual['especialidad']);
		if ($clave_principal !== '' && isset($items[$clave_principal])) {
			$items = [$clave_principal => $items[$clave_principal]] + $items;
		}
	}

	if ($propia_transaccion) {
		$conexion->begin_transaction();
	}

	try {
		$stmt = $conexion->prepare('DELETE FROM tecnico_especialidad WHERE id_tecnico = ?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$stmt->close();

		$stmt = $conexion->prepare('INSERT INTO tecnico_especialidad (id_tecnico, especialidad, clave) VALUES (?, ?, ?)');
		foreach ($items as $clave => $texto) {
			$clave_db = (string) $clave;
			$stmt->bind_param('iss', $id, $texto, $clave_db);
			$stmt->execute();
		}
		$stmt->close();

		$principal = reset($items);
		$stmt = $conexion->prepare('UPDATE tecnico SET especialidad = ? WHERE id_tecnico = ?');
		$stmt->bind_param('si', $principal, $id);
		$stmt->execute();
		$stmt->close();

		if ($propia_transaccion) {
			$conexion->commit();
		}
	} catch (Throwable $e) {
		if ($propia_transaccion) {
			$conexion->rollback();
		}
		throw $e;
	}

	return true;
}
