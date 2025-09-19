AI Assistant model configuration
--------------------------------

You can control the OpenAI models and guard token usage via environment variables:

- AI_ASSISTANT_MODEL: Chat model used by the AI assistant (default: gpt-4o-mini)
- OPENAI_EMBEDDING_MODEL: Model used for embeddings (default: text-embedding-3-small)
- AI_ASSISTANT_HISTORY_LIMIT: Max chat messages from history to include (default: 12)
- AI_ASSISTANT_HISTORY_TEXT_LIMIT: Max characters per history message (default: 2000)
- AI_ASSISTANT_MAX_CONTEXT_FRAGMENTS: Max context fragments to include (default: 24)
- AI_ASSISTANT_FRAGMENT_TEXT_LIMIT: Max characters per context fragment text (default: 800)
- AI_ASSISTANT_INCLUDE_CONTEXT_JSON: Whether to send summarized context JSON to the model (default: true)

Set them in your .env file as needed, e.g.:

AI_ASSISTANT_MODEL=gpt-4o
OPENAI_EMBEDDING_MODEL=text-embedding-3-large
AI_ASSISTANT_HISTORY_LIMIT=10
AI_ASSISTANT_MAX_CONTEXT_FRAGMENTS=20
