@extends('legal.layout')

@section('title', 'Política de Privacidad - Juntify')

@section('content')
    <h1>Política de Privacidad</h1>
    <div class="legal-meta">
        <span><strong>Responsable:</strong> Juntify (Juntify Labs S.L., CIF/NIF provisional: B00000000)</span>
        <span><strong>Contacto de privacidad:</strong> <a href="mailto:privacy@juntify.com">privacy@juntify.com</a></span>
        <span><strong>Normativa aplicable:</strong> Reglamento (UE) 2016/679 (RGPD), Ley Orgánica 3/2018 (LOPDGDD) y demás legislación española y de la Unión Europea en materia de protección de datos.</span>
    </div>

    <p>Esta Política de Privacidad describe cómo Juntify trata los datos personales de todas las personas usuarias de la plataforma, incluyendo cuentas gratuitas, administradores/as de organizaciones, miembros de equipos, desarrolladores/as, personal con rol de auditoría y usuarios/as invitados. El uso de nuestros servicios implica la aceptación de esta política.</p>

    <h2>1. Datos personales que tratamos</h2>
    <p>Dependiendo del tipo de cuenta y de las funcionalidades utilizadas, Juntify puede tratar las siguientes categorías de datos:</p>
    <ul>
        <li><strong>Datos de identificación:</strong> nombre completo, nombre de usuario, dirección de correo electrónico, organización asociada y roles asignados en la plataforma.</li>
        <li><strong>Datos de autenticación:</strong> contraseñas cifradas, tokens de acceso, registros de inicio de sesión, trazas de actividad y direcciones IP para fines de seguridad.</li>
        <li><strong>Datos operativos:</strong> reuniones programadas, resúmenes, transcripciones, archivos de audio, notas, tareas, contactos y comentarios generados dentro de la aplicación.</li>
        <li><strong>Datos derivados de integraciones:</strong> identificadores de Google Workspace/Drive, permisos concedidos, resultados de sincronización y documentos transferidos por la persona usuaria.</li>
        <li><strong>Datos de análisis inteligente:</strong> prompts y respuestas del asistente de IA, metadatos utilizados para entrenar modelos privados y trazabilidad de decisiones automatizadas.</li>
        <li><strong>Datos de facturación:</strong> país, plan contratado, estado de pago, identificadores de cobro y comprobantes emitidos por nuestro proveedor de pagos (Mercado Pago).</li>
        <li><strong>Soporte y cumplimiento:</strong> incidencias registradas, tickets de soporte, comunicaciones con el equipo de Juntify y evidencia recopilada para auditorías o investigaciones de seguridad.</li>
    </ul>

    <h2>2. Finalidades y bases jurídicas</h2>
    <p>Tratamos los datos personales con las siguientes finalidades y fundamentos jurídicos:</p>
    <ul>
        <li><strong>Prestación del servicio (ejecución de contrato):</strong> crear cuentas, gestionar organizaciones, facilitar la colaboración en reuniones y tareas, generar transcripciones y proveer funcionalidades de inteligencia artificial.</li>
        <li><strong>Seguridad y cumplimiento (interés legítimo y obligación legal):</strong> prevenir accesos no autorizados, monitorizar incidentes, auditar cambios críticos, conservar registros legales y colaborar con autoridades competentes.</li>
        <li><strong>Integraciones con terceros (consentimiento informado):</strong> sincronizar archivos con Google Drive, procesar pagos a través de Mercado Pago y habilitar funciones impulsadas por OpenAI u otros proveedores tecnológicos.</li>
        <li><strong>Soporte y mejora continua (interés legítimo):</strong> analizar métricas de uso, personalizar la experiencia según el rol asignado, evaluar el rendimiento de modelos de IA y ejecutar pruebas de calidad.</li>
        <li><strong>Comunicaciones comerciales (consentimiento):</strong> enviar novedades sobre planes, características y eventos cuando la persona usuaria lo autoriza expresamente. Puede retirarse el consentimiento en cualquier momento.</li>
    </ul>

    <h2>3. Conservación de los datos</h2>
    <p>Los datos se conservan durante la vigencia de la cuenta y posteriormente durante los plazos necesarios para cumplir con obligaciones legales o contractuales. Eliminamos o anonimizamos la información cuando deja de ser necesaria, salvo que exista una obligación legal de conservación. Las copias de seguridad y los registros de actividad se rotan y purgan de acuerdo con políticas internas de retención basadas en el principio de minimización.</p>

    <h2>4. Destinatarios y transferencias internacionales</h2>
    <p>Juntify comparte datos con terceros únicamente cuando es imprescindible para operar la plataforma:</p>
    <ul>
        <li><strong>Google LLC:</strong> sincronización de archivos, calendarios y almacenamiento en Google Drive/Workspace. Las transferencias se amparan en las Cláusulas Contractuales Tipo de la Comisión Europea.</li>
        <li><strong>Mercado Pago:</strong> procesamiento de cobros y gestión de facturación. Solo se transmiten los datos indispensables para completar la transacción.</li>
        <li><strong>OpenAI y otros proveedores de IA:</strong> generación de resúmenes, análisis y funcionalidades inteligentes. Aplicamos mecanismos de seudonimización, filtros de contenido y registros de auditoría para limitar la exposición de datos sensibles.</li>
        <li><strong>Infraestructura y monitorización:</strong> servicios cloud, herramientas de seguridad y sistemas de soporte técnico con los que se han firmado acuerdos de tratamiento de datos.</li>
    </ul>
    <p>No vendemos ni cedemos datos personales a terceros para fines comerciales. Cualquier transferencia internacional se evalúa previamente para garantizar un nivel de protección equivalente al exigido por el RGPD.</p>

    <h2>5. Derechos de las personas usuarias</h2>
    <p>Puedes ejercer tus derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento, portabilidad y a no ser objeto de decisiones automatizadas. Para hacerlo, envía una solicitud a <a href="mailto:privacy@juntify.com">privacy@juntify.com</a> indicando el derecho que deseas ejercer. Si gestionas una organización, debes asegurarte de canalizar internamente las solicitudes de tus miembros antes de remitirlas a Juntify.</p>
    <p>En caso de desacuerdo, tienes derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (www.aepd.es) u otra autoridad de control competente.</p>

    <h2>6. Seguridad y confidencialidad</h2>
    <p>Implementamos medidas técnicas y organizativas alineadas con el principio de «seguridad desde el diseño», incluyendo cifrado en tránsito y reposo, registros de actividad por rol, segmentación de entornos, autenticación reforzada y revisiones periódicas de permisos. Las personas con roles privilegiados (superadmin, developer, founder) están sujetas a cláusulas adicionales de confidencialidad y controles de acceso granular.</p>

    <h2>7. Uso de la plataforma por menores</h2>
    <p>Juntify está orientado a organizaciones y profesionales adultos. No permitimos el registro de menores de 16 años sin la autorización verificable de sus progenitores o tutores legales. Si detectamos que se ha creado una cuenta sin las garantías exigibles, procederemos a su cancelación.</p>

    <h2>8. Cambios en la política</h2>
    <p>Podemos actualizar esta Política de Privacidad para reflejar cambios normativos, tecnológicos o de negocio. Publicaremos la nueva versión indicando la fecha de actualización y, cuando proceda, solicitaremos tu consentimiento nuevamente.</p>

    <p>Si tienes dudas sobre esta política o sobre el tratamiento de tus datos personales, contáctanos en <a href="mailto:privacy@juntify.com">privacy@juntify.com</a>.</p>
@endsection
