import { createHash } from 'node:crypto';

const CANONICAL_ROLES = ['administrador', 'recepcionista', 'caja_finanzas', 'medico', 'super_admin'];

function normalizedArray(value) {
  return Array.isArray(value) ? value.map((item) => String(item).trim()).filter(Boolean) : [];
}

function section(label, values) {
  return values.length ? `${label}:\n${values.map((value) => `- ${value}`).join('\n')}` : '';
}

function documentContent(entry) {
  const escalation = entry.escalation?.allowed && entry.escalation?.message
    ? [`Permitido: sí. ${entry.escalation.message}`]
    : ['Permitido: no.'];

  return [
    `Título: ${entry.title}`,
    `Pregunta: ${entry.question}`,
    `Respuesta: ${entry.answer}`,
    section('Pasos', normalizedArray(entry.steps)),
    section('Restricciones en línea', normalizedArray(entry.online_restrictions)),
    section('Formas alternativas de preguntar', normalizedArray(entry.aliases)),
    section('Escalado', escalation),
  ].filter(Boolean).join('\n\n');
}

export function buildAssistantDocuments(knowledgeBase) {
  if (!knowledgeBase || !Array.isArray(knowledgeBase.entries) || !knowledgeBase.defaults) {
    throw new Error('La base de conocimiento no tiene el esquema esperado.');
  }

  const catalogRoles = Object.keys(knowledgeBase.catalogs?.roles ?? {});
  if (catalogRoles.some((role) => !CANONICAL_ROLES.includes(role))) {
    throw new Error('La base de conocimiento contiene un rol no canónico.');
  }

  const defaults = knowledgeBase.defaults;
  const moduleCatalog = knowledgeBase.catalogs?.modules ?? {};
  const forbiddenPhrases = normalizedArray(knowledgeBase.catalogs?.forbidden_phrases)
    .map((phrase) => phrase.toLocaleLowerCase('es-EC'));
  const technicalEvidencePattern = /(?:^|[\s(])(?:app|bootstrap|config|database|resources|routes|storage|vendor)[/\\]|(?:^|\s)\.env\b|\b(?:artisan|composer\.json|package\.json)\b|\.php\b/iu;
  const documents = [];

  for (const rawEntry of knowledgeBase.entries) {
    const entry = {
      ...defaults,
      ...rawEntry,
      escalation: { ...(defaults.escalation ?? {}), ...(rawEntry.escalation ?? {}) },
    };
    const roles = normalizedArray(entry.roles);
    const modules = normalizedArray(entry.modules);
    const version = Number.isInteger(entry.version) ? entry.version : Number(defaults.version ?? 1);
    const content = documentContent(entry);
    const normalizedContent = content.toLocaleLowerCase('es-EC');

    for (const phrase of forbiddenPhrases) {
      if (normalizedContent.includes(phrase)) {
        throw new Error(`El documento ${entry.id} contiene una frase prohibida: ${phrase}`);
      }
    }

    if (technicalEvidencePattern.test(content)) {
      throw new Error(`El documento ${entry.id} contiene evidencia tecnica interna no autorizada.`);
    }

    for (const role of roles) {
      if (!CANONICAL_ROLES.includes(role)) {
        throw new Error(`Rol no canónico en ${entry.id}: ${role}`);
      }

      const roleModules = modules.filter((module) =>
        normalizedArray(moduleCatalog[module]?.roles).includes(role));
      if (roleModules.length === 0) {
        throw new Error(`El documento ${entry.id} no tiene módulos autorizados para ${role}.`);
      }

      documents.push({
        document_id: `${entry.id}:${role}:v${version}`,
        content,
        metadata: {
          entry_id: entry.id,
          role,
          modules: roleModules,
          locale: entry.locale,
          status: entry.status,
          knowledge_version: version,
          requires_online: Boolean(entry.requires_online),
          source: 'knowledge-base.json',
        },
      });
    }
  }

  documents.sort((left, right) => {
    if (left.document_id < right.document_id) return -1;
    if (left.document_id > right.document_id) return 1;
    return 0;
  });

  const checksumInput = JSON.stringify({
    knowledge_schema_version: knowledgeBase.schema_version,
    documents,
  });
  const checksum = createHash('sha256').update(checksumInput, 'utf8').digest('hex');

  return {
    schema_version: 1,
    source: knowledgeBase.source ?? 'resources/assistant/knowledge-base.json',
    locale: defaults.locale ?? 'es-EC',
    knowledge_schema_version: knowledgeBase.schema_version,
    knowledge_version: knowledgeBase.schema_version,
    document_count: documents.length,
    checksum_algorithm: 'sha256',
    checksum,
    documents,
  };
}

export { CANONICAL_ROLES };
