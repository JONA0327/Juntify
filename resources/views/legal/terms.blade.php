@extends('legal.layout')

@section('title', 'Condiciones de Uso - Juntify')

@section('content')
    <h1>Condiciones de Uso</h1>
    <div class="legal-meta">
        <span><strong>Proveedor del servicio:</strong> Juntify (Juntify Labs S.L.)</span>
        <span><strong>Contacto:</strong> <a href="mailto:soporte@juntify.com">soporte@juntify.com</a></span>
        <span><strong>Normativa de referencia:</strong> RGPD, LOPDGDD, Ley de Servicios de la Sociedad de la Información (LSSI-CE) y legislación mercantil española.</span>
    </div>

    <p>Las presentes Condiciones de Uso regulan el acceso y utilización de la plataforma Juntify, sus aplicaciones móviles, APIs y servicios relacionados. Al crear una cuenta aceptas íntegramente estos términos, así como la Política de Privacidad y cualquier acuerdo específico aplicable a tu plan de servicio.</p>

    <h2>1. Objetivo del servicio</h2>
    <p>Juntify facilita la gestión integral de reuniones, tareas y colaboraciones mediante funciones de agenda, transcripción automatizada, analítica impulsada por IA, integración con Google Workspace/Drive y procesamiento de pagos de suscripción. El servicio está dirigido a organizaciones, equipos y profesionales que requieren gestionar información sensible con altos estándares de seguridad.</p>

    <h2>2. Tipos de cuentas y responsabilidades</h2>
    <ul>
        <li><strong>Cuenta gratuita (free):</strong> acceso limitado a funcionalidades básicas. Debes utilizar la plataforma para fines legítimos y respetar los límites de uso establecidos.</li>
        <li><strong>Miembros y administradores de organización:</strong> gestionan usuarios, grupos, permisos, carpetas en la nube y contenido compartido. Son responsables de garantizar que la información cargada cumple la normativa aplicable y que sus miembros respetan estas condiciones.</li>
        <li><strong>Roles avanzados (superadmin, developer, founder):</strong> disponen de privilegios para configurar integraciones, auditar registros y aplicar cambios estructurales. Su uso debe ceñirse a políticas internas de seguridad, segregación de funciones y controles de acceso.</li>
        <li><strong>Usuarios invitados:</strong> cuentan con permisos acotados para participar en reuniones puntuales. No pueden extraer información fuera del contexto autorizado.</li>
    </ul>

    <h2>3. Registro y autenticación</h2>
    <ol>
        <li>Debes proporcionar información veraz, actualizada y completa. El uso de identidades falsas o credenciales compartidas está prohibido.</li>
        <li>Las credenciales son personales e intransferibles. Debes protegerlas mediante contraseñas robustas y autenticación multifactor cuando esté disponible.</li>
        <li>El acceso mediante cuentas de terceros (por ejemplo, Google) implica aceptar las condiciones del proveedor correspondiente.</li>
    </ol>

    <h2>4. Uso aceptable</h2>
    <p>Queda expresamente prohibido:</p>
    <ul>
        <li>Introducir contenidos ilegales, difamatorios, discriminatorios o que vulneren derechos de terceros.</li>
        <li>Utilizar la plataforma para enviar spam, malware, ingeniería social o técnicas de scraping automatizado.</li>
        <li>Intentar eludir o comprometer las medidas de seguridad, realizar pruebas de intrusión sin autorización o acceder a datos ajenos.</li>
        <li>Revender, sublicenciar o compartir el acceso con terceros no autorizados.</li>
    </ul>
    <p>Juntify se reserva el derecho de suspender o cancelar cuentas que incumplan estas normas y de notificar a las autoridades competentes si se detectan actividades ilícitas.</p>

    <h2>5. Contenido y propiedad intelectual</h2>
    <p>La titularidad de los materiales cargados por las personas usuarias pertenece a dichas personas u organizaciones. Concedes a Juntify una licencia limitada para alojar, procesar y analizar dicho contenido con el fin de prestar el servicio. Juntify mantiene todos los derechos sobre su tecnología, diseños, marcas y documentación.</p>

    <h2>6. Servicios de terceros</h2>
    <p>La plataforma se integra con proveedores externos como Google Workspace, Mercado Pago y OpenAI. El uso de estas integraciones está sujeto a los términos de dichos proveedores. Juntify actúa como intermediario tecnológico y no controla las condiciones que cada tercero pueda imponer. Informaremos a las personas usuarias de cambios relevantes y trabajaremos para minimizar el impacto en la continuidad del servicio.</p>

    <h2>7. Planes, pagos y cancelaciones</h2>
    <ul>
        <li>Los planes de pago se facturan a través de Mercado Pago. Al confirmar el pago autorizas a dicho proveedor a gestionar el cobro periódico conforme al plan contratado.</li>
        <li>Puedes cancelar la suscripción desde la configuración de tu perfil o solicitándolo a soporte. La cancelación se hará efectiva al finalizar el periodo ya abonado.</li>
        <li>Los importes satisfechos no son reembolsables salvo que la legislación aplicable establezca lo contrario o que Juntify ofrezca garantías adicionales por escrito.</li>
    </ul>

    <h2>8. Confidencialidad y protección de datos</h2>
    <p>Las obligaciones de privacidad se regulan en la Política de Privacidad. Cada organización debe disponer de una base legitimadora para tratar los datos que incorpora a Juntify. Cuando actúes como responsable del tratamiento te comprometes a cumplir el RGPD, la LOPDGDD y cualquier normativa sectorial que sea de aplicación.</p>

    <h2>9. Disponibilidad y soporte</h2>
    <p>Nos esforzamos por mantener un servicio disponible y actualizado. Podemos realizar mantenimientos programados que se comunicarán por los canales habituales. Juntify no será responsable de incidencias causadas por proveedores externos, fuerza mayor o configuraciones ajenas a nuestro control, aunque trabajaremos para reducir su impacto.</p>

    <h2>10. Limitación de responsabilidad</h2>
    <p>En la medida permitida por la ley, la responsabilidad total de Juntify por daños directos se limita a la cantidad abonada en los doce meses anteriores al incidente reclamado. No seremos responsables de daños indirectos, lucro cesante o pérdida de reputación derivados del uso de la plataforma, salvo dolo o negligencia grave.</p>

    <h2>11. Modificaciones de los términos</h2>
    <p>Podemos actualizar estas Condiciones de Uso para reflejar mejoras del servicio, cambios normativos o ajustes comerciales. Te notificaremos con antelación razonable y, si continúas usando la plataforma, se entenderá que aceptas la nueva versión. Si no estás de acuerdo, deberás cancelar tu cuenta y dejar de usar el servicio.</p>

    <h2>12. Legislación aplicable y jurisdicción</h2>
    <p>Estas Condiciones se rigen por la legislación española. Cualquier controversia se someterá a los juzgados y tribunales de Madrid (España), salvo que la normativa imperativa disponga otra cosa.</p>

    <p>Si tienes preguntas sobre estas Condiciones de Uso, contacta con <a href="mailto:soporte@juntify.com">soporte@juntify.com</a>.</p>
@endsection
