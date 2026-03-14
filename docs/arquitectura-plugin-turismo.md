# Arquitectura propuesta: Plugin WordPress modular de turismo

## 1) Objetivo funcional

Crear un plugin modular para WordPress que permita:

- Buscar **paquetes reales existentes** (custom post type o posts ya cargados) con filtros como destino, fechas, tipo de viaje y presupuesto.
- Mostrar resultados con disponibilidad y precios base.
- Si no hay resultados, presentar **destinos sugeridos** y CTA de contacto:
  - WhatsApp (mensaje pre-armado)
  - Email (formulario o `mailto`)
- Incluir un **cotizador** que capture requisitos y derive la consulta al canal elegido.

---

## 2) Estructura de carpetas y archivos (modular)

```text
plugin-buscador-cotizador/
├─ plugin-buscador-cotizador.php               # Bootstrap principal
├─ uninstall.php                               # Limpieza opcional de datos del plugin
├─ readme.txt                                  # Metadatos WP.org (opcional)
├─ languages/
│  ├─ plugin-buscador-cotizador-es_AR.po
│  └─ plugin-buscador-cotizador-es_AR.mo
├─ assets/
│  ├─ css/
│  │  ├─ frontend.css
│  │  └─ admin.css
│  ├─ js/
│  │  ├─ frontend-search.js
│  │  ├─ frontend-quote.js
│  │  └─ admin-settings.js
│  └─ img/
├─ templates/
│  ├─ search-form.php
│  ├─ search-results.php
│  ├─ no-results-suggestions.php
│  ├─ quote-form.php
│  └─ partials/
│     ├─ package-card.php
│     ├─ filters.php
│     └─ cta-contact.php
├─ includes/
│  ├─ Core/
│  │  ├─ Plugin.php                            # Orquestador (singleton/service container)
│  │  ├─ Loader.php                            # Registro de hooks/actions/filters
│  │  ├─ Activator.php
│  │  ├─ Deactivator.php
│  │  ├─ I18n.php
│  │  └─ Assets.php
│  ├─ Modules/
│  │  ├─ Search/
│  │  │  ├─ SearchModule.php
│  │  │  ├─ SearchService.php                  # Reglas de negocio de búsqueda
│  │  │  ├─ SearchRepository.php               # WP_Query / meta query / tax query
│  │  │  ├─ SearchController.php               # AJAX/REST endpoints
│  │  │  └─ SearchShortcode.php                # [tour_search]
│  │  ├─ Packages/
│  │  │  ├─ PackagesModule.php
│  │  │  ├─ PackagePostType.php                # CPT opcional: tour_package
│  │  │  ├─ PackageTaxonomies.php              # destino, tipo_viaje, temporada
│  │  │  ├─ PackageMeta.php                    # precio, duración, cupos, fecha_salida
│  │  │  └─ PackageSyncService.php             # Adaptadores con contenido existente
│  │  ├─ Quote/
│  │  │  ├─ QuoteModule.php
│  │  │  ├─ QuoteService.php                   # Motor de cotización básica
│  │  │  ├─ QuoteController.php                # Submit de cotizador
│  │  │  ├─ QuoteShortcode.php                 # [tour_quote]
│  │  │  └─ QuoteNotifier.php                  # Email + payload WhatsApp
│  │  ├─ Suggestions/
│  │  │  ├─ SuggestionsModule.php
│  │  │  ├─ SuggestionsService.php             # Sugerencias por popularidad/temporada
│  │  │  └─ SuggestionsShortcode.php           # [tour_suggestions]
│  │  ├─ Contact/
│  │  │  ├─ ContactModule.php
│  │  │  ├─ WhatsAppService.php
│  │  │  ├─ EmailService.php
│  │  │  └─ ContactShortcode.php               # [tour_contact_cta]
│  │  └─ Admin/
│  │     ├─ AdminModule.php
│  │     ├─ SettingsPage.php                   # Ajustes generales
│  │     ├─ SettingsRegistry.php               # register_setting / sections / fields
│  │     ├─ LeadsListTable.php                 # Opcional: tabla de leads
│  │     └─ AdminNotices.php
│  ├─ Integrations/
│  │  ├─ ElementorIntegration.php              # Widgets opcionales
│  │  ├─ GutenbergBlocksIntegration.php        # Bloques dinámicos
│  │  └─ WooCommerceIntegration.php            # Si se cotiza como producto
│  ├─ Api/
│  │  ├─ RestRoutes.php                        # /wp-json/tourism/v1/*
│  │  ├─ AjaxRoutes.php                        # wp_ajax_* y nopriv
│  │  └─ Validators.php
│  ├─ Data/
│  │  ├─ Repositories/
│  │  │  ├─ PackageRepositoryInterface.php
│  │  │  └─ WpPackageRepository.php
│  │  └─ DTO/
│  │     ├─ SearchCriteria.php
│  │     └─ QuoteRequest.php
│  ├─ Security/
│  │  ├─ Nonce.php
│  │  ├─ Sanitizer.php
│  │  └─ Capability.php
│  └─ Helpers/
│     ├─ Formatter.php
│     ├─ Logger.php
│     └─ Url.php
├─ config/
│  ├─ defaults.php                             # Defaults configurables
│  └─ capabilities.php
└─ tests/
   ├─ Unit/
   ├─ Integration/
   └─ E2E/
```

---

## 3) Módulos (responsabilidades)

### 3.1 Core
- Inicializa el plugin y carga módulos activos.
- Registra hooks globales, i18n, assets y ciclo de vida.

### 3.2 Packages
- Define cómo representar “paquetes turísticos” en WordPress.
- Soporta 2 modos:
  1. **Modo nativo:** CPT `tour_package`.
  2. **Modo adaptador:** reutilizar posts/CPT existentes mapeando campos.

### 3.3 Search
- Recibe criterios de búsqueda y consulta paquetes reales existentes.
- Criterios sugeridos:
  - destino
  - fecha (rango)
  - cantidad de pasajeros
  - presupuesto
  - tipo de viaje
- Devuelve resultados paginados y ordenables.

### 3.4 Suggestions (fallback)
- Se dispara cuando `total_results = 0`.
- Genera sugerencias por:
  - destinos más consultados
  - temporada activa
  - presupuesto cercano al ingresado

### 3.5 Quote
- Captura datos de interés para cotización personalizada.
- Puede calcular un estimado básico (no vinculante).
- Genera lead interno + dispara notificaciones.

### 3.6 Contact
- Construye mensaje prellenado para WhatsApp con contexto de búsqueda/cotización.
- Envía correo al equipo comercial con los datos estructurados.

### 3.7 Admin
- Configuración general y operativa.
- Gestión de campos mapeados y comportamiento de fallback.
- Métricas básicas de conversiones (búsquedas → contacto).

---

## 4) Modelo de datos

## Entidades principales

### `tour_package` (o entidad mapeada)
Campos recomendados:
- `post_title`: nombre del paquete
- `post_content`: descripción
- `meta`:
  - `_tour_price_from` (decimal)
  - `_tour_currency` (ARS/USD)
  - `_tour_duration_days` (int)
  - `_tour_departure_dates` (array/json)
  - `_tour_capacity` (int)
  - `_tour_featured` (bool)
  - `_tour_contact_only` (bool)
- taxonomías:
  - `tour_destination`
  - `tour_trip_type`
  - `tour_season`

### `tour_lead` (opcional CPT privado o tabla custom)
- nombre
- email
- whatsapp
- origen (`search_no_results`, `quote_form`, etc.)
- criterios de búsqueda serializados
- canal elegido
- fecha/hora

> Recomendación: comenzar con CPT privado para velocidad de implementación; migrar a tabla custom cuando el volumen de leads crezca.

---

## 5) Shortcodes y bloques

Shortcodes mínimos:
- `[tour_search]` → formulario + resultados en la misma vista.
- `[tour_quote]` → formulario de cotización.
- `[tour_suggestions]` → carrusel/grid de destinos sugeridos.
- `[tour_contact_cta]` → botones WhatsApp + email.

Atributos útiles:
- `[tour_search layout="vertical" results_per_page="6" show_filters="1"]`
- `[tour_quote source="landing-verano"]`
- `[tour_contact_cta whatsapp="1" email="1" prefill="1"]`

Evolución recomendada:
- Agregar bloques Gutenberg dinámicos equivalentes a cada shortcode.

---

## 6) Flujo del usuario (frontend)

1. Usuario entra a landing de turismo.
2. Completa buscador (`[tour_search]`).
3. El módulo Search consulta paquetes reales en WordPress.
4. **Si hay resultados:**
   - se muestran cards de paquete
   - CTA: ver detalle / solicitar cotización
5. **Si no hay resultados:**
   - mensaje contextual (“No encontramos paquetes para esos criterios”)
   - módulo Suggestions muestra destinos alternativos
   - CTA directa a WhatsApp o Email con datos prellenados
6. Usuario envía cotización/contacto.
7. Se registra lead y se notifica al equipo.
8. En admin, se visualizan conversiones y se ajusta estrategia.

---

## 7) Panel de administración

Menú sugerido: **Turismo → Buscador & Cotizador**

Secciones:

1. **General**
   - Activar/desactivar módulos
   - Moneda, formato de fecha, textos base

2. **Datos de Paquetes**
   - Modo de fuente: CPT propio / contenido existente
   - Mapeo de campos (meta keys / taxonomías)

3. **Búsqueda**
   - Filtros habilitados
   - Orden por defecto
   - Cantidad de resultados por página

4. **Sin resultados (Fallback)**
   - Activar sugerencias
   - Criterio de sugerencias (populares, temporada, manual)
   - Texto/CTA del estado vacío

5. **Contacto y Notificaciones**
   - Número de WhatsApp
   - Plantilla de mensaje
   - Email receptor de leads
   - Asunto y plantilla de correo

6. **Leads y analítica básica**
   - Listado de consultas
   - Origen de conversión
   - Export CSV

7. **Avanzado**
   - REST/AJAX mode
   - Logs y depuración
   - Permisos por rol

---

## 8) API interna (sugerida)

REST endpoints ejemplo:
- `GET /wp-json/tourism/v1/search`
- `POST /wp-json/tourism/v1/quote`
- `GET /wp-json/tourism/v1/suggestions`
- `POST /wp-json/tourism/v1/lead/contact`

Reglas:
- Validación estricta de payload.
- Sanitización y escape en entrada/salida.
- Nonce/capabilities para acciones sensibles.

---

## 9) Seguridad y cumplimiento

- Sanitizar todos los campos (`sanitize_text_field`, `sanitize_email`, etc.).
- Escape en render (`esc_html`, `esc_attr`, `esc_url`).
- Nonces en formularios y acciones AJAX.
- Rate limiting básico para evitar spam en formularios.
- Honeypot o reCAPTCHA opcional para cotizador.
- Política de privacidad: informar almacenamiento de leads.

---

## 10) Roadmap de implementación (fases)

### Fase 1 (MVP)
- Bootstrap + Core + Admin Settings básico
- Módulo Search con consulta real sobre CPT existente
- Fallback sin resultados con Suggestions
- CTA WhatsApp + Email
- Shortcode `[tour_search]`

### Fase 2
- Módulo Quote completo (`[tour_quote]`)
- Registro de leads (CPT privado)
- Métricas básicas y export CSV

### Fase 3
- Bloques Gutenberg
- Integraciones (Elementor/WooCommerce)
- Capa de cache y optimización avanzada

---

## 11) Criterios de aceptación de arquitectura

- Modularidad: cada módulo desacoplado, con responsabilidades claras.
- Reutilización: buscador opera sobre paquetes existentes sin duplicar data.
- Experiencia resiliente: ante 0 resultados, siempre hay ruta de conversión.
- Escalabilidad: repositorios e interfaces permiten cambiar origen de datos.
- Operabilidad: panel admin suficiente para negocio sin tocar código.

