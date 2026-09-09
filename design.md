# Atlas Tools — Design System

Guía visual y de interacción para construir nuevas tools dentro de Atlas Tools. Este documento mantiene la identidad de la aplicación actual y debe aplicarse a V0, V1, V2 y V3.

Para CardWorks, esta guía se utiliza junto con [`CARDWORKS_SYSTEM.md`](CARDWORKS_SYSTEM.md). La especificación funcional define qué debe hacer el sistema; este documento define cómo debe organizarse, presentarse y sentirse.

## Dirección visual

Atlas Tools debe sentirse como una colección de herramientas pequeñas, útiles y cuidadas: editorial, directa y humana. La interfaz debe priorizar claridad, espacio en blanco y controles fáciles de entender.

Evitar:

- Dashboards genéricos de estilo SaaS.
- Gradientes fuertes, sombras pesadas o exceso de tarjetas.
- Colores saturados fuera de la paleta definida.
- Métricas decorativas que no ayuden a tomar una decisión.
- Lenguaje técnico innecesario para el usuario final.

La interfaz debe conservar el tono actual: “pequeñas soluciones. Cero complicaciones.”

## Fundamentos

### Paleta

Usar estos tokens como fuente única de color:

```css
:root {
    --ink: #18221d;
    --paper: #f7f6f1;
    --sage: #dce4d8;
    --lime: #d7f45b;
    --line: #ccd1ca;
    --muted: #687169;
}
```

Uso recomendado:

- `--paper`: fondo principal.
- `--ink`: texto principal, navegación, botones oscuros y estados activos.
- `--sage`: hero de una tool, superficies destacadas y agrupaciones positivas.
- `--lime`: acciones principales, selección activa y énfasis interactivo.
- `--line`: bordes y divisores.
- `--muted`: texto secundario, ayudas y metadatos.

Colores semánticos adicionales solo cuando sean necesarios:

```css
:root {
    --success-bg: #eef7e8;
    --success-line: #aac69c;
    --error-bg: #fff0ed;
    --error-line: #e1a89e;
    --warning-bg: #fff8dc;
    --warning-line: #d7bf70;
}
```

Para KPIs usar semántica sobria:

- Verde salvia: desempeño favorable.
- Lima: selección, foco o acción disponible.
- Amarillo suave: atención requerida.
- Rojo suave: error, incumplimiento o bloqueo.
- No usar rojo y verde como único indicador; acompañar siempre con texto o icono.

### Tipografía

La aplicación usa Google Fonts:

- `DM Sans`: interfaz, navegación, tablas, botones, formularios y datos.
- `Instrument Serif`: titulares, nombres de tools y números destacados.

```css
body {
    font-family: "DM Sans", sans-serif;
}

h1,
h2,
h3,
.display {
    font-family: "Instrument Serif", serif;
    font-weight: 400;
}
```

Escala base:

- Texto principal: `16px`.
- Texto secundario: `13px–14px`.
- Metadatos y etiquetas: `11px–12px`.
- Títulos de sección: `40px–52px`.
- Hero principal: `58px–102px`, responsive.
- Números KPI grandes: `36px–56px` usando Instrument Serif.

Los encabezados deben tener interlineado compacto y tracking ligeramente negativo. Las etiquetas tipo kicker deben usar mayúsculas y `letter-spacing: .16em`.

### Bordes y profundidad

- Bordes de `1px solid var(--line)`.
- Preferir superficies planas.
- Usar sombras únicamente en hover de cards disponibles.
- No usar bordes redondeados generales.
- El círculo se reserva para marcas, iconos, estados compactos y controles claramente circulares.

## Estructura de página

### Header global

Mantener el header actual:

- Altura aproximada: `86px`.
- Padding horizontal: `6vw` en desktop y `22px` en móvil.
- Línea inferior de `1px`.
- Marca izquierda con círculo oscuro, letra “A” en Instrument Serif y acento lima.
- Navegación simple, con links subrayados.

No convertir el header en una barra administrativa pesada. Si aparecen nuevas secciones, mantener navegación corta y clara.

### Hero de tool

Cada tool debe iniciar con un bloque hero en `--sage`:

- Link de retorno arriba.
- Kicker de tool, por ejemplo `TOOL 002`.
- Título con una palabra enfatizada en verde apagado.
- Descripción breve, máximo aproximadamente 650px de ancho.
- Mucho espacio vertical, sin llenar el área con controles.

Ejemplo conceptual:

```html
<section class="tool-hero">
    <a class="back-link" href="/">← Volver a herramientas</a>
    <span class="kicker">TOOL 002</span>
    <h1>CardWorks <em>Insights</em></h1>
    <p>Una descripción clara de lo que resuelve la herramienta.</p>
</section>
```

### Workspace

El contenido operativo debe vivir en un contenedor centrado:

- Ancho máximo recomendado: `1180px`.
- Padding desktop: aproximadamente `65px 30px 100px`.
- Separación vertical entre bloques: `24px`.
- En móvil: padding horizontal de `24px`.

Usar secciones blancas con borde fino para configuraciones, tablas y resultados. No poner toda la página dentro de una tarjeta gigante.

## Componentes

### Buttons

Clases y comportamiento base:

- `.button`: inline-flex, padding amplio, tipografía heredada, cursor pointer.
- `.button-dark`: fondo `--ink`, texto blanco; para acciones secundarias o navegación fuerte.
- `.button-accent`: fondo `--lime`, texto `--ink`, borde `--ink`; para la acción principal.
- `.text-button`: fondo transparente y subrayado; para acciones menores como cambiar archivo.

Los botones deben usar verbos claros: “Cargar archivo”, “Aplicar filtros”, “Guardar regla”, “Generar scorecard”, “Exportar”.

Mantener el patrón de flecha para acciones importantes: `→` o `↓`.

### Kicker y step labels

Usar una jerarquía editorial consistente:

```css
.kicker,
.step {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .16em;
}
```

Los kickers identifican una sección o tool. Los steps identifican una etapa de configuración. No usarlos como texto decorativo repetido.

### Panels

```css
.panel {
    border: 1px solid var(--line);
    background: #fff;
    padding: 38px;
}
```

En móvil usar aproximadamente `24px` de padding. Los paneles deben tener un título, una breve descripción y una tarea concreta.

### KPI cards

Las tarjetas KPI deben ser compactas y funcionales:

- Título de métrica en DM Sans, 12px–13px.
- Valor principal grande en Instrument Serif.
- Unidad visible: `%`, segundos, llamadas, encuestas, etc.
- Comparación contra meta o período anterior.
- Muestra o cobertura cuando el dato lo requiera.
- Estado textual además del color.

No crear una fila interminable de tarjetas. Mostrar solo los KPIs seleccionados o los más importantes para la vista.

Ejemplo de contenido:

```text
COMPLIANCE
94%
Meta 90% · 63 evaluaciones
Sobre meta
```

### Filters

Los filtros deben presentarse como controles de trabajo, no como badges:

- Fecha o período.
- Cuenta: CS Merrick o CS Open Sky.
- Supervisor.
- FM.
- LOB.
- Wave.
- Tenure bucket.
- Estado laboral cuando sea relevante.

`ON/OFF` proveniente de horarios no es un estado laboral y no debe aparecer como filtro de attrition. Los estados laborales son Activo, Licencia, Transferido y Salida.

Agrupar filtros en una franja blanca con borde. Mantener visibles los filtros principales y colocar los secundarios bajo “Más filtros” si la cantidad crece.

Los filtros deben mostrar el estado actual y tener un botón claro para aplicar o limpiar. No agregar filtros que no existan en los datos.

### Tables

Las tablas son parte central de la tool.

- Fondo blanco.
- Encabezado en `#eef0eb` o una variante derivada de `--sage`.
- Bordes inferiores de `1px solid var(--line)`.
- Padding aproximado: `12px`.
- Texto de datos en 13px.
- Encabezados claros y cortos.
- Scroll horizontal controlado en pantallas pequeñas.
- Ordenamiento y paginación solo cuando aporten valor.

En tablas de agentes mostrar siempre el nombre y los campos de agrupación necesarios. No mostrar IP, email, diagnóstico, observación médica ni otros campos personales por defecto.

### Tabs

Conservar el estilo actual de tabs:

- Fondo blanco.
- Borde fino.
- Texto oscuro.
- Estado activo en `--ink` con texto blanco.
- Metadatos secundarios con opacidad reducida.

Usarlos para cambiar entre vistas relacionadas, no para ocultar información esencial.

### Scorecards

El scorecard debe sentirse como un reporte editorial imprimible:

- Encabezado con cuenta, supervisor o nivel, período y fecha de generación.
- Resumen breve con resultado general.
- Tabla principal de métricas seleccionadas.
- Columnas recomendadas: métrica, resultado, meta, brecha, peso y estado.
- Notas o acciones al final.
- Separación clara entre resultados operativos y comentarios.

Los scorecards operativos se generan a nivel cuenta, FM o supervisor. El Bonus Generator produce además un comprobante individual por agente; no debe confundirse con el scorecard operativo.

### Upload area

Mantener el patrón visual de carga de Excel actual:

- Dropzone grande con borde discontinuo.
- Fondo blanco.
- Icono circular en `--sage`.
- Título en Instrument Serif.
- Ayuda breve en `--muted`.
- Archivo seleccionado en una franja delimitada.
- Mensaje de privacidad visible, sin prometer más de lo que el sistema realmente garantiza.

Estados mínimos:

- Vacío.
- Archivo seleccionado.
- Arrastrando archivo.
- Procesando.
- Exitoso.
- Error de validación.

## Dashboard Master KPI

La vista principal debe mantener el lenguaje de Atlas Tools, pero puede ser más densa que la landing page.

Orden recomendado:

1. Hero compacto con nombre de la cuenta y período.
2. Filtros principales.
3. Selector segmentado `Agentes | Supervisores`.
4. KPIs prioritarios.
5. Tendencias o comparación temporal.
6. Tabla del nivel seleccionado.
7. Acceso discreto al centro de calidad cuando existan datos no conciliados.

El Master KPI usa la identidad canónica y la asignación histórica vigente para cada fecha. Todos los agentes iniciales del roster se consideran activos. Los registros no conciliados no aparecen como si fueran agentes válidos: se conservan en el centro de calidad hasta resolver su identidad.

Attrition aparece únicamente en la vista de supervisor o un nivel superior. En la vista por agente se muestra el estado laboral o un evento de salida, nunca una tasa individual.

## Arquitectura de información de CardWorks

La navegación interna debe ser corta, predecible y basada en tareas:

1. Resumen.
2. Dashboards.
3. Scorecards.
4. Bonos.
5. Excepciones.
6. Agentes.
7. Datos.
8. Chat AI.

En desktop puede utilizarse una barra lateral estrecha debajo del header global. En móvil debe convertirse en un menú accesible. No saturar el header principal de Atlas Tools con todas estas opciones.

`Dashboards` abre un segundo nivel con VOC, Efficiencies y Compliance. `Bonos` abre Planes, Corridas y Resultados. `Datos` abre Importaciones, Calidad y Conciliación.

La navegación debe conservar el período y los filtros compatibles al cambiar entre dashboards. No debe conservar filtros incompatibles de forma silenciosa.

## Shell de aplicación

Las pantallas analíticas comparten una estructura:

1. Header global de Atlas Tools.
2. Navegación de CardWorks.
3. Contexto de página: título, propósito y actualización de datos.
4. Barra de filtros compartida.
5. Contenido principal.
6. Acciones de exportación o administración según permisos.

El hero completo en salvia se utiliza en la entrada a CardWorks, cargas iniciales y estados de onboarding. Los dashboards diarios deben usar un encabezado compacto para no desplazar la información crítica debajo del primer viewport.

El ancho máximo puede ampliarse hasta `1440px` en tablas analíticas, manteniendo márgenes laterales respirables. Formularios, asistentes y páginas de detalle deben conservar un ancho de lectura de aproximadamente `900px–1100px`.

## Barra de filtros compartida

Jerarquía recomendada:

- Primera fila: período, cuenta, supervisor y agente.
- Segunda fila o `Más filtros`: FM, LOB, wave, tenure y estado laboral.
- Acciones finales: Aplicar y Limpiar.

Comportamiento:

- El período siempre permanece visible.
- Los filtros dependientes se actualizan según selecciones anteriores.
- Al cambiar un filtro padre, los valores hijos inválidos se eliminan con un aviso breve.
- Mostrar la cantidad de filtros adicionales activos.
- Permitir guardar vistas frecuentes en una fase posterior, sin convertirlo en requisito inicial.
- Reflejar los filtros en la URL cuando no contengan datos sensibles.
- Mostrar una línea de resumen al exportar: `Agosto 2026 · CS Merrick · Todos los supervisores`.

Los selectores con muchas opciones necesitan búsqueda. Un agente debe mostrarse como nombre y BMS; el identificador sirve para desambiguar, no para dominar la interfaz.

## Controles analíticos compartidos

### Selector Agentes/Supervisores

Usar un control segmentado de dos opciones:

```text
[ Agentes ] [ Supervisores ]
```

- La opción activa usa fondo `--ink` y texto blanco.
- El cambio conserva fecha, cuenta, LOB, FM, wave y tenure cuando sean compatibles.
- Al cambiar a Supervisores aparece Attrition.
- Al volver a Agentes, Attrition desaparece y se muestra estado laboral.
- El encabezado de tabla y la descripción deben confirmar el nivel actual.

### Indicador de frescura

Cada pantalla analítica muestra `Actualizado hasta` con la fecha mínima común entre las fuentes necesarias. Si una fuente está atrasada, usar estado de advertencia y explicar qué métricas afecta.

### Comparación temporal

Cuando se muestre variación:

- Indicar período comparado.
- Usar flecha, texto y color.
- Invertir la semántica en métricas donde menor es mejor.
- No mostrar una mejora como positiva si la muestra no es comparable.

### Muestras

Toda tarjeta de VOC, Compliance, Transfer o AHT debe mostrar su denominador. Ejemplos:

```text
87.4%
126 encuestas
```

```text
284 s
4,921 contactos
```

Una muestra insuficiente se muestra con la etiqueta `Muestra insuficiente`, no con un estado de cumplimiento definitivo.

## Pantalla Resumen / Master KPI

### Propósito

Responder rápidamente quién cumple, quién necesita atención y si los datos son confiables.

### Composición

1. Título, período y actualización.
2. Filtros compartidos.
3. Selector Agentes/Supervisores.
4. Resumen compacto de población, cobertura y alertas.
5. Tabla Master KPI.
6. Drawer o página de detalle al seleccionar una fila.

### Tabla

Columnas fijadas a la izquierda:

- Agente o supervisor.
- Cuenta.
- Contexto organizativo esencial.

Columnas KPI:

- AHT.
- Compliance.
- ADH.
- Transfer.
- CSAT.
- Professionalism.
- NPS.
- Attrition solamente en supervisor.

Cada celda KPI debe poder mostrar:

- Resultado.
- Estado contra meta.
- Muestra en tooltip o segunda línea.
- Indicador de excepción.
- Indicador de datos incompletos.

No pintar toda la tabla con colores. Usar texto, pequeños marcadores semánticos y fondos suaves solo en estados que requieren atención.

La fila seleccionada abre un detalle sin perder los filtros. En agente, el detalle muestra tendencia, composición de métricas, excepciones y plan de bono aplicable. En supervisor, muestra equipo, distribución y attrition.

## Dashboard VOC

Orden recomendado:

1. CSAT, Professionalism, NPS y encuestas.
2. Tendencia temporal compartida.
3. Distribución Promoter/Neutral/Detractor.
4. Comparación por supervisor o agente.
5. Tabla de detalle.

Visualizaciones:

- Línea para tendencia.
- Barras apiladas al 100% para distribución de respuestas.
- Barras horizontales para ranking.

No usar gauges. Para NPS, mostrar claramente su escala de `-100` a `100`. Los gráficos deben mantener el mismo color para Promoter, Neutral y Detractor en toda la aplicación.

## Dashboard Efficiencies

Orden recomendado:

1. AHT, Transfer, Talk, Hold, ACW y contactos.
2. Tendencia temporal.
3. Composición de AHT.
4. Relación entre volumen y eficiencia.
5. Tabla de detalle.

Visualizaciones:

- Línea para AHT y Transfer, con ejes o gráficos separados cuando las escalas difieran.
- Barras apiladas para Talk, Hold y ACW por contacto.
- Scatter opcional para volumen frente a AHT.

Todo tiempo debe mostrar unidad. Para valores grandes, usar `4m 44s`; en tablas exportables conservar además segundos. No mezclar totales y promedios sin una etiqueta explícita.

## Dashboard Compliance

Orden recomendado:

1. Score, pass rate, evaluaciones y errores críticos.
2. Tendencia.
3. Failure rate por atributo.
4. Interaction Type.
5. Causal factors.
6. Tabla de evaluaciones autorizada.

El listado de atributos puede ser largo. Usar barras horizontales ordenadas por failure rate y permitir búsqueda. `Not Applicable` debe mostrarse separado de `Missing`.

El detalle de una evaluación se abre en drawer o página dedicada. Mostrar solo información operativa necesaria; los permisos determinan si se visualizan comentarios completos.

## Scorecards operativos

### Pantalla de generación

- Período.
- Nivel: cuenta, FM o supervisor.
- Selección de una o varias entidades.
- Comparación opcional.
- Vista previa.
- Exportación.

### Resultado

El scorecard utiliza una composición imprimible:

1. Identidad y período.
2. Resultado general o resumen ejecutivo.
3. Tabla de KPI con resultado, meta, brecha, muestra y estado.
4. Attrition cuando aplique.
5. Calidad de datos y notas.
6. Fecha, versión y responsable de generación.

En impresión no deben aparecer navegación, filtros ni botones. Los colores deben conservar significado en escala de grises mediante texto e iconos.

## Administración de agentes

### Listado

Mostrar:

- Nombre y BMS.
- Supervisor y FM vigentes.
- Cuenta, LOB y wave.
- Estado laboral real.
- Completitud de identidad.
- Fecha efectiva del último movimiento.

Los valores de horario `ON/OFF` nunca aparecen en la columna Estado.

### Detalle

Usar tabs o secciones:

- Perfil.
- Identificadores.
- Asignaciones.
- Eventos de estado.
- Excepciones.
- Historial de cambios.

Las líneas de tiempo deben mostrar vigencias sin superposición. Registrar salida, transferencia o reingreso requiere un diálogo de confirmación con resumen de impacto.

### Alta de agente

El formulario debe priorizar BMS, nombre, fecha efectiva y asignación. Genesys ID puede quedar pendiente con advertencia. Antes de guardar, mostrar posibles coincidencias para evitar duplicados.

## Bonus Plan Builder

El constructor debe ser un asistente de cuatro pasos:

1. Información y vigencia.
2. Población y asignación.
3. Métricas, metas y pesos.
4. Reglas, simulación y publicación.

### Editor de métricas

Usar una tabla editable con:

- Activar/desactivar.
- Métrica.
- Meta.
- Dirección.
- Peso.
- Muestra mínima.
- Curva de payout.
- Regla de datos insuficientes.

Mostrar una barra fija de suma de pesos:

```text
Peso asignado 85% de 100%     Faltan 15%
```

- Verde salvia al llegar a 100%.
- Amarillo cuando falte peso.
- Rojo suave cuando exceda 100%.
- Deshabilitar Publicar mientras el total no sea válido.

La simulación debe mostrar al menos tres agentes representativos o permitir seleccionar agentes. Debe marcarse claramente como simulación y nunca guardarse como pago.

Un plan publicado se muestra en modo lectura. La acción disponible es `Crear nueva versión`, no editar silenciosamente.

## Bonus Generator

### Inicio de corrida

Presentar una configuración breve:

- Período.
- Plan.
- Población.
- Base de bono cuando aplique.
- Fuentes requeridas y su frescura.

Antes de calcular, mostrar un resumen de agentes incluidos, excluidos y sin plan.

### Revisión

La pantalla principal de una corrida incluye:

- Estado de la corrida.
- Conteos: listos, advertencias, bloqueados y excepciones pendientes.
- Tabla por agente.
- Acciones según permisos.

Columnas:

- Agente.
- Plan y versión.
- Attainment.
- Bono calculado.
- Excepciones.
- Calidad de datos.
- Estado.

Seleccionar un agente abre el detalle métrico con columnas `Original`, `Ajuste`, `Final`, `Meta`, `Peso`, `Factor` y `Puntos`.

### Cierre

`Aprobar` y `Bloquear` son acciones diferentes. Bloquear requiere confirmación reforzada y muestra que los resultados dejarán de cambiar. Una corrida bloqueada usa una banda visual sobria con identificador, fecha y aprobador.

## Comprobante individual de bono

Debe sentirse personal, claro e imprimible:

- Nombre y período.
- Plan aplicado.
- Resultado general.
- Tabla detallada de métricas.
- Excepciones visibles para el agente, sin información médica privada.
- Bono final y estado.
- Nota sobre redondeos o muestras insuficientes.

No usar lenguaje punitivo. Preferir `Debajo de la meta` sobre `Falló` y explicar siempre la base del cálculo.

## Centro de excepciones

### Bandeja

Separar por estado:

- Pendientes.
- Aprobadas.
- Rechazadas.
- Revocadas.

Cada fila muestra agente, origen, rango de fechas, métricas afectadas, acción propuesta, estado y revisor. Diagnóstico y observación sensible no aparecen en el listado.

### Detalle y aprobación

Organizar en tres bloques:

1. Evidencia mínima del evento.
2. Impacto propuesto por métrica.
3. Auditoría y decisión.

Mostrar una comparación antes/después. La aprobación debe exigir motivo cuando se cambie la acción recomendada. Los overrides manuales requieren una advertencia más fuerte y, si la política lo define, dos aprobadores.

Las excepciones ya utilizadas en una corrida bloqueada muestran la referencia de esa corrida y no se editan en sitio.

## Attrition

Attrition solo aparece en vistas agregadas. Su tarjeta debe mostrar tres elementos juntos:

```text
ATTRITION
3.2%
3 salidas · HC promedio 94.1
```

El detalle permite ver las salidas que componen el numerador, respetando permisos. Una transferencia o licencia debe identificarse como excluida, no desaparecer silenciosamente.

Registrar una salida desde el perfil de agente debe mostrar:

- Fecha efectiva.
- Clasificación.
- Inclusión o exclusión de attrition.
- Asignación a la que se atribuirá.
- Impacto en períodos abiertos.

## Centro de datos y calidad

### Importaciones

Cada carga muestra:

- Fuente y período.
- Archivo.
- Usuario.
- Fecha.
- Estado.
- Filas aceptadas, rechazadas y no conciliadas.

El estado de procesamiento debe actualizarse sin bloquear toda la página. Un archivo fallido conserva un reporte descargable de errores.

### Calidad

Priorizar problemas accionables:

- Fuente atrasada.
- Identidades no conciliadas.
- Duplicados.
- Asignaciones superpuestas.
- Valores fuera de rango.
- Corridas afectadas.

Cada problema debe enlazar a la acción que lo resuelve. Evitar una pantalla técnica llena de códigos sin explicación.

### Conciliación

Mostrar el identificador de fuente, coincidencias sugeridas y contexto suficiente. El usuario puede vincular a un agente, crear uno nuevo o dejar pendiente. Una vinculación manual requiere confirmación y queda auditada.

## Chat AI

El Chat AI puede vivir como panel lateral persistente en desktop y pantalla completa en móvil.

Debe incluir:

- Preguntas sugeridas basadas en la pantalla actual.
- Contexto visible de período y filtros.
- Respuestas con métricas, muestra y fuente lógica.
- Tablas compactas y enlaces para abrir el dashboard filtrado.
- Distinción clara entre consulta, explicación y simulación.

Ejemplos de prompts visibles:

- “¿Qué supervisores están debajo de la meta de Compliance?”
- “Explícame el bono de este agente.”
- “Compara CSAT y NPS contra el período anterior.”
- “Simula los pesos 20/25/15/10/10/10/10.”

Las simulaciones usan una superficie diferenciada y el texto `No modifica datos`. Si el usuario solicita aprobar, editar o registrar algo, el asistente debe dirigirlo al flujo correspondiente y no afirmar que la acción ya fue realizada.

El Chat AI nunca debe mostrar diagnósticos médicos, observaciones disciplinarias completas ni datos fuera del alcance del usuario.

## Estados y etiquetas del dominio

Usar etiquetas de texto consistentes:

- `Sobre meta`
- `En meta`
- `Debajo de meta`
- `Sin datos`
- `Muestra insuficiente`
- `No aplica`
- `Excepción pendiente`
- `Excepción aplicada`
- `Datos incompletos`
- `Bloqueado`

Estados de planes:

- Borrador.
- Publicado.
- Retirado.

Estados de corrida:

- Borrador.
- Calculada.
- En revisión.
- Aprobada.
- Bloqueada.
- Pagada.
- Anulada.

No utilizar únicamente color para estos estados. Cada uno necesita texto y, cuando ayude, un icono simple.

## Gráficos

Reglas comunes:

- Título que describa la comparación, no solo el tipo de gráfico.
- Unidad visible en eje o tooltip.
- Leyenda cercana y consistente.
- Tooltip con fecha, valor, muestra y comparación cuando exista.
- Línea de meta discreta y etiquetada.
- Máximo de series simultáneas que puedan distinguirse con claridad.
- Tabla alternativa o resumen textual accesible.
- No usar gráficos 3D, velocímetros ni donuts para comparaciones precisas.

Colores recomendados para categorías de VOC:

- Promoter: `--success-line` con fondo derivado de `--success-bg`.
- Neutral: `--warning-line`.
- Detractor: `--error-line`.

Las series de agente o supervisor pueden usar variaciones de ink, sage y muted. El lima se reserva para foco, selección o meta, no para cada serie.

## Drawers, modales y confirmaciones

- Usar drawer para explorar una fila sin perder filtros.
- Usar página dedicada para formularios extensos, historial y Bonus Plan Builder.
- Usar modal solamente para decisiones cortas o confirmaciones.
- Acciones de salida, override, publicación, bloqueo y anulación deben resumir consecuencias.
- El botón destructivo debe nombrar la acción, por ejemplo `Registrar salida`, no `Aceptar`.
- Cerrar un modal no debe descartar trabajo extenso sin advertencia.

## Diseño para permisos

Los controles no autorizados pueden ocultarse cuando no aporten contexto. Si el usuario necesita entender que una acción existe pero requiere otro rol, mostrarla deshabilitada con una explicación breve.

No mostrar espacios vacíos que revelen información restringida. Un supervisor ve su equipo; un agente ve su comprobante aprobado; un usuario de HR autorizado accede a la evidencia mínima necesaria.

## Formato de valores

- Porcentajes operativos: una decimal por defecto.
- NPS: una decimal o entero según configuración, siempre en escala explícita.
- Tiempos principales: minutos y segundos legibles.
- Tablas técnicas/exportables: segundos además del formato legible.
- Conteos: enteros con separador de miles.
- Dinero: moneda del plan, dos decimales cuando corresponda.
- Fechas: formato legible en interfaz y formato ISO en exportaciones técnicas.
- Valores no disponibles: `—` acompañado por estado, nunca `0` por conveniencia.

## Diseño de impresión y exportación

Scorecards y comprobantes deben tener estilos de impresión propios:

- Fondo blanco.
- Sin navegación ni controles.
- Márgenes seguros.
- Encabezado repetible cuando una tabla cruza páginas.
- Número de página.
- Identificador de corrida o reporte.
- Estado visible: simulación, aprobado o bloqueado.
- Evitar partir una fila métrica entre páginas.

La vista previa en pantalla debe corresponder razonablemente al archivo exportado.

## Responsive

Breakpoints recomendados:

- Desktop: más de `1100px`.
- Tablet: `761px–1100px`.
- Mobile: hasta `760px`.

En móvil:

- Ocultar navegación secundaria si no cabe.
- Pasar grids de tres columnas a una columna.
- Apilar filtros.
- Convertir barras de acción en columnas.
- Mantener tablas con scroll horizontal.
- Reducir títulos, no comprimirlos hasta volverlos ilegibles.
- Mantener targets interactivos de al menos 40px de altura.

## Interacción y estados

Todo control debe tener estados visibles:

- Hover.
- Focus visible.
- Selected.
- Disabled.
- Loading.
- Success.
- Error.
- Empty state.

Usar `outline` visible en focus. No depender únicamente del color.

Los estados vacíos deben explicar qué falta y cuál es la acción siguiente. Ejemplo: “Carga un archivo para ver los KPIs”.

Los errores deben aparecer cerca de la acción que falló y explicar cómo corregirla en lenguaje sencillo.

## Accesibilidad

- Usar HTML semántico.
- Asociar labels con inputs.
- Mantener contraste suficiente entre texto y fondo.
- No usar solo color para distinguir desempeño.
- Incluir nombres accesibles en iconos y botones.
- Mantener navegación por teclado.
- Usar tablas con encabezados reales.
- Hacer que los gráficos tengan título, leyenda o alternativa textual.

## Privacidad y datos sensibles

Los datos pertenecen a agentes y pueden incluir información laboral sensible.

Por defecto no mostrar en dashboards generales:

- Diagnósticos.
- Observaciones médicas.
- Clínicas.
- Doctores.
- Emails.
- IPs.
- Teléfonos.
- Identificadores que no sean necesarios para la operación.

Las vistas relacionadas con incapacidades o notificaciones deben estar separadas, protegidas por permisos y mostrar únicamente los datos mínimos necesarios.

## Reglas de implementación visual

- Reutilizar los tokens y patrones existentes antes de crear estilos nuevos.
- Mantener los estilos globales en `resources/css/app.css`.
- Crear estilos específicos por tool en archivos separados, siguiendo el patrón de `resources/css/unpivot.css`.
- No introducir otra familia tipográfica sin justificación.
- No introducir otra paleta sin aprobación.
- No utilizar componentes visuales con apariencia de otro producto.
- Evitar dependencias de UI innecesarias.
- Mantener nombres de clases descriptivos y consistentes.
- Verificar desktop y mobile antes de finalizar.
- Revisar que títulos, números, tablas y botones no queden cortados.

## Criterio de calidad

Una nueva vista está lista cuando:

- Se reconoce visualmente como parte de Atlas Tools.
- La jerarquía de información es clara en menos de unos segundos.
- El usuario sabe cuál es la acción principal.
- Los datos importantes son legibles sin zoom adicional.
- Los estados de carga, vacío y error están resueltos.
- La vista funciona en desktop y mobile.
- No expone datos sensibles por defecto.
- Los colores, tipografías, bordes y espaciados respetan este documento.
