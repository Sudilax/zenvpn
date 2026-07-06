# ZenVPN Local Dev Workflow

## Stack
- **Editor**: VS Code (project folder: ZenVpn)
- **Agent**: OpenCode (CLI), configured via `opencode.json`
- **Model host**: LM Studio (headless service, GPU-resident on RTX 3050 8GB)
- **Models**:
  - `qwen/qwen3.5-9b` → **Planner**
  - `qwen2.5-coder-7b-instruct` → **Coder / QA / Reviewer / DevOps**
- **Persistent memory**: `.md` spec files in the project folder (NOT chat history — sessions don't persist across restarts)
- **Safety net**: git commit after every reviewed change

---

## The Core Loop

```
1. PLAN     → Planner model breaks feature into steps
2. WRITE    → Coder model implements ONE step
3. REVIEW   → Coder model (fresh ask) reviews its own diff
4. VERIFY   → You read the diff
5. COMMIT   → git commit
6. REPEAT for next step
```

Do NOT ask for a whole feature/milestone in one Coder request. One scoped step per request = better quality, easier review, easier rollback.

---

## Step-by-Step in OpenCode

### 1. Start of session — always re-establish context
Local models have zero memory of past sessions. Every time you open OpenCode fresh:
```
Read [SPEC_FILE].md. Summarize where we left off and what the next step is.
```

### 2. Planning a new feature
```
/models
```
→ select `local/qwen/qwen3.5-9b`

```
I want to add [feature]. Break this into milestones and concrete steps.
Consider edge cases and security implications. Do not write code.
```

Save its output into a spec file (e.g. `ZENVPN_[FEATURE]_PLAN.md`) in the project folder — this is what survives between sessions, not the chat.

### 3. Implementing one step
```
/models
```
→ select `local/qwen2.5-coder-7b-instruct`

```
Implement step [X.Y] from [SPEC_FILE].md: [paste the specific step].
Keep changes scoped to this step only.
```

### 4. Reviewing that step
Same model, same session, explicit role switch:
```
Now review the code you just wrote as a security-focused reviewer.
Check for edge cases, race conditions, error handling, and anything
that contradicts the spec.
```

### 5. You verify + commit
```
git add .
git commit -m "Step X.Y: [short description]"
```
Never skip this — it's your rollback point if a later step goes wrong.

### 6. Next step
Repeat step 3-5 for the next item in the spec, in the same session if context allows.

---

## Model Switching Rules

- **Within one sitting**: switching models with `/models` keeps the conversation thread — the new model can see prior messages up to its context limit. No need to re-paste everything.
- **Switch cost**: ~10-15s to load a different model into VRAM (only one fits at a time on 8GB). Budget for this when moving between Plan → Code phases.
- **Between sittings** (closing/reopening OpenCode): always re-read the relevant spec file first — there is no memory carryover.
- **If a switch seems to lose context** (ask "what did we just discuss?" to check): re-paste the relevant spec section rather than assuming continuity.

---

## Context Window Management

- Current context: 8192–16384 tokens depending on model load settings.
- If you hit `n_keep >= n_ctx` errors: the conversation + project scan exceeded the window. Fix by:
  - Reloading the model with a larger `--context-length`, watching VRAM headroom in Task Manager, OR
  - Starting a fresh, shorter session and re-pointing it at the spec file instead of relying on long chat history

---

## When to Escalate Beyond Local Models

Stay local for: routine implementation, config/UI work, tests, standard review passes.

Consider Ollama Cloud (devstral-small-2:24b or gemma4:31b-cloud) or a fresh planning session elsewhere for:
- Novel protocol-level logic (VPN handshake, encryption specifics)
- Security-critical code you want a second opinion on
- Anything where the Planner/Coder models visibly struggle or produce shallow output after a couple of tries

---

## File Organization Convention

```
ZenVpn/
├── opencode.json                          # provider/model config
├── WORKFLOW.md                            # this file
├── ZENVPN_PROJECT_STATE.md                # one-time full export (current state, architecture)
├── ZENVPN_[FEATURE]_PLAN.md               # one spec file per feature, created by Planner
└── [actual project code]
```

One spec file per feature keeps things scannable — don't cram every plan into one giant document.
