from google import genai
from google.genai import types
from google.genai.errors import ClientError
import os, sys, json, time, random
import difflib, math

# ---- DIAG: Python-side timing (safe to keep in prod) ----
_PY_T0 = time.time()
def _diag(label, **kw):
    # emit to STDERR so PHP captures it without polluting the streamed content
    dt = int((time.time() - _PY_T0) * 1000)
    parts = [f"PYDIAG {label} dt_ms={dt}"]
    for k, v in kw.items():
        parts.append(f"{k}={v}")
    sys.stderr.write(" ".join(parts) + "\n")
    sys.stderr.flush()

_diag("py_enter")
# ---------------------------------------------------------

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="strict")
    sys.stderr.reconfigure(encoding="utf-8", errors="strict")


def _parse_args():
    argv = sys.argv[1:]
    flags = {a for a in argv if a.startswith("--")}
    pos   = [a for a in argv if not a.startswith("--")]
    return {
        "si_text1":        pos[0] if len(pos) > 0 else "",
        "user_input":      pos[1] if len(pos) > 1 else "",
        "stream_mode":     ("--stream" in flags),
        "meta_from_stdin": ("--meta-from-stdin" in flags),
    }

def _align_supports_to_answer(answer_text: str, supports: list) -> list:
    if not isinstance(answer_text, str) or not answer_text or not supports:
        return []

    aligned = []
    scan_from = 0

    for s in supports:
        seg_txt = (s.get("text") or "").strip()
        if not seg_txt:
            continue

        i = answer_text.find(seg_txt, scan_from)
        if i == -1:
            i = answer_text.find(seg_txt)
        if i == -1:
            continue

        end = i + len(seg_txt)
        scan_from = end

        aligned.append({
            "grounding_chunk_indices": s.get("grounding_chunk_indices", []) or [],
            "text": seg_txt,
            "segment_end_index": end,
        })

    return aligned

def _normalize_aligned_supports(supports_aligned, sources, streamed_answer_md):
    """
    - Clamp segment_end_index to [0, len(streamed_answer_md)]
    - Dedup and clamp grounding_chunk_indices to [0, len(sources)-1]
    - Drop supports that end up with no valid indices
    - Sort ascending by segment_end_index (so callers can splice DESC safely)
    """
    L = len(streamed_answer_md or "")
    N = len(sources or [])
    out = []
    for s in supports_aligned or []:
        j = int(s.get("segment_end_index") or 0)
        if j < 0: j = 0
        if j > L: j = L

        seen = set()
        idxs = []
        for k in (s.get("grounding_chunk_indices") or []):
            if isinstance(k, int) and 0 <= k < N and k not in seen:
                seen.add(k); idxs.append(k)
        if not idxs:
            continue

        out.append({
            "grounding_chunk_indices": idxs,
            "text": s.get("text", ""),
            "segment_end_index": j,
        })
    out.sort(key=lambda x: x["segment_end_index"])
    return out

def generate():
    args = _parse_args()
    si_text1        = args["si_text1"]
    user_input      = args["user_input"]
    stream_mode     = args["stream_mode"]
    meta_from_stdin = args["meta_from_stdin"]

    base_dir = os.path.dirname(os.path.abspath(__file__))
    #credential_path = os.path.join(base_dir, "rosy-booth-451302-c9-048513ee69b1.json")
    credential_path = os.path.join(base_dir, "application_default_credentials.json")
    os.environ['GOOGLE_APPLICATION_CREDENTIALS'] = credential_path
    project_id = "rosy-booth-451302-c9"
    location   = "global"
    model      = "gemini-2.5-flash"

    #disable_vas = (os.getenv("AI_DISABLE_VAS") == "1")
    #_diag("vas_mode", off=str(disable_vas))
    #_diag("before_client_init")
    #client = genai.Client(vertexai=True, project=project_id, location=location)
    #_diag("after_client_init")
    disable_vas = (os.getenv("AI_ENABLE_VAS_STREAM") != "1" and stream_mode) or (os.getenv("AI_DISABLE_VAS") == "1")
    _diag("vas_mode", off=str(disable_vas))

    client = genai.Client(vertexai=True, project=project_id, location=location)

    contents = [types.Content(role="user", parts=[types.Part.from_text(text=user_input)])]

    tools = [] if disable_vas else [
        types.Tool(
            retrieval=types.Retrieval(
                vertex_ai_search=types.VertexAISearch(
                    datastore="projects/rosy-booth-451302-c9/locations/global/collections/default_collection/dataStores/digest-full_1751591809641"
                )
            )
        )
    ]

    config = types.GenerateContentConfig(
        temperature=0.0,#0.1,
        top_p=1,
        seed=0,
        max_output_tokens=4096,
        safety_settings=[
            types.SafetySetting(category="HARM_CATEGORY_HATE_SPEECH",      threshold="OFF"),
            types.SafetySetting(category="HARM_CATEGORY_DANGEROUS_CONTENT", threshold="OFF"),
            types.SafetySetting(category="HARM_CATEGORY_SEXUALLY_EXPLICIT", threshold="OFF"),
            types.SafetySetting(category="HARM_CATEGORY_HARASSMENT",        threshold="OFF"),
        ],
        thinking_config=types.ThinkingConfig(thinking_budget=0),
        tools=(None if disable_vas else tools),
        system_instruction=[types.Part.from_text(text=si_text1)],
    )

    # ---------------
    # STREAMING MODE
    # ---------------
    if stream_mode:
        def stream_once():
            if hasattr(client.models, "generate_content_stream"):
                return client.models.generate_content_stream(model=model, contents=contents, config=config)
            return client.models.generate_content(model=model, contents=contents, config=config, stream=True)

        attempts, delay = 3, 1.0
        for i in range(attempts):
            try:
                _diag("before_llm_call", attempt=i)
                resp_stream = stream_once()
                _diag("after_llm_submit", attempt=i)

                _first = True
                for event in resp_stream:
                    if _first:
                        _first = False
                        _diag("first_token", attempt=i)
                    chunk = getattr(event, "text", None)
                    if chunk:
                        sys.stdout.write(chunk); sys.stdout.flush()
                try:
                    _ = resp_stream.get_final_response()
                except Exception:
                    pass
                sys.stdout.write("\n"); sys.stdout.flush()
                _diag("stream_done", attempt=i)
                return
            except Exception as e:
                _diag("error", err=str(e.__class__.__name__), attempt=i)
                is_429 = isinstance(e, ClientError) and getattr(e, "status_code", None) == 429 or "RESOURCE_EXHAUSTED" in str(e)
                if not is_429 or i == attempts - 1:
                    sys.stderr.write(f"[stream-error] {e}\n"); sys.stderr.flush()
                    try:
                        resp = client.models.generate_content(model=model, contents=contents, config=config)
                        sys.stdout.write(getattr(resp, "text", "") or ""); sys.stdout.write("\n"); sys.stdout.flush()
                    except Exception as ee:
                        sys.stderr.write(f"[nonstream-fallback-error] {ee}\n"); sys.stderr.flush()
                    return
                time.sleep(delay + random.random())
                delay *= 2
        return

    # ------------------------------------
    # META-FROM-STDIN MODE (for citation)
    # ------------------------------------
    if meta_from_stdin:
      streamed_answer_md = sys.stdin.read()

      try:
          resp = client.models.generate_content(model=model, contents=contents, config=config)
      except Exception as e:
          print(json.dumps({"sources": [], "supports": [], "queries": []}, ensure_ascii=False), end="")
          return

      meta_text   = getattr(resp, "text", "") or ""
      candidates  = getattr(resp, "candidates", []) or []
      candidate   = candidates[0] if candidates else None
      gm          = getattr(candidate, "grounding_metadata", None) if candidate else None

      supports_raw, sources, queries = [], [], []
      if gm:
        idx_map = {}uri_to_new = {}

        chunks = (getattr(gm, "grounding_chunks", []) or [])
        for orig_idx, ch in enumerate(chunks):
            rc = getattr(ch, "retrieved_context", None)
            uri = getattr(rc, "uri", None) if rc else None
            if uri:
                if uri not in uri_to_new:
                    uri_to_new[uri] = len(sources)
                    sources.append(uri)
                idx_map[orig_idx] = uri_to_new[uri]

        for sup in (getattr(gm, "grounding_supports", []) or []):
            seg = getattr(sup, "segment", None)
            old_idxs = (getattr(sup, "grounding_chunk_indices", []) or [])
            new_idxs, seen = [], set()
            for i in old_idxs:
                try:
                    k = int(i)
                    if k in idx_map:
                        mapped = idx_map[k]
                        if mapped not in seen:
                            seen.add(mapped)
                            new_idxs.append(mapped)
                except Exception:
                    continue

            supports_raw.append({
                "grounding_chunk_indices": new_idxs,
                "text": (getattr(seg, "text", "") if seg else "") or "",
                "segment_end_index": int(getattr(seg, "end_index", 0) or 0),
            })

        queries = [q for q in (getattr(gm, "retrieval_queries", []) or []) if q is not None]

      def build_index_mapper(a: str, b: str):
        sm = difflib.SequenceMatcher(a=a, b=b)
        ops = sm.get_opcodes()
        if not ops:
            def map_idx(i: int) -> int:
                return max(0, min(len(b), int(i)))
            return map_idx

        def map_idx(i: int) -> int:
            if i <= 0:
                tag, i1, i2, j1, j2 = ops[0]
                return j1
            for tag, i1, i2, j1, j2 in ops:
                if i < i1:
                    return j1
                if i <= i2:
                    if i2 == i1:
                        return j1
                    span_a = i2 - i1
                    span_b = j2 - j1
                    t = (i - i1) / span_a
                    j = j1 + t * span_b
                    j = int(max(0, min(len(b), round(j))))
                    return j
            return len(b)

        return map_idx
      
      map_meta_to_stream = build_index_mapper(meta_text, streamed_answer_md)
      
      def find_or_map(seg_text: str, meta_end: int, streamed: str) -> int:
        if seg_text:
            j_hint = map_meta_to_stream(meta_end)
            window = max(20, min(200, len(seg_text) * 3))
            start  = max(0, j_hint - window)
            end    = min(len(streamed), j_hint + window)

            k = streamed.find(seg_text, start, end)
            if k == -1:
                k = streamed.find(seg_text)

            if k != -1:
                j = k + len(seg_text)
                if j < len(streamed) and streamed[j:j+1].isalnum():
                    t = min(len(streamed), j + 8)
                    while j < t and streamed[j:j+1].isalnum():
                        j += 1
                return j

        return map_meta_to_stream(meta_end)

      supports_aligned = []
      for sup in supports_raw:
          seg_txt  = (sup.get("text") or "").strip()
          meta_end = int(sup.get("segment_end_index") or 0)
          j = find_or_map(seg_txt, meta_end, streamed_answer_md)

          idxs = sup.get("grounding_chunk_indices") or []
          if 0 < j <= len(streamed_answer_md) and idxs:
              supports_aligned.append({
                  "grounding_chunk_indices": idxs,
                  "text": seg_txt,
                  "segment_end_index": j,
              })

      supports_aligned = _normalize_aligned_supports(supports_aligned, sources, streamed_answer_md)

      if not supports_aligned and supports_raw:
        supports_aligned = _normalize_aligned_supports(
            _align_supports_to_answer(streamed_answer_md, supports_raw),
            sources,
            streamed_answer_md
      )

      out = {"sources": sources, "supports": supports_aligned, "queries": queries}
      print(json.dumps(out, ensure_ascii=False), end="")
      return

    # ---------------------------------
    # NON-STREAM JSON MODE (legacy/test)
    # ---------------------------------
    response = client.models.generate_content(model=model, contents=contents, config=config)

    text       = getattr(response, "text", "") or ""
    candidates = getattr(response, "candidates", []) or []
    candidate  = candidates[0] if candidates else None
    gm         = getattr(candidate, "grounding_metadata", None) if candidate else None

    supports, sources, queries = [], [], []
    if gm:
        idx_map = {}
        uri_to_new = {}

        chunks = (getattr(gm, "grounding_chunks", []) or [])
        for orig_idx, ch in enumerate(chunks):
            rc = getattr(ch, "retrieved_context", None)
            uri = getattr(rc, "uri", None) if rc else None
            if uri:
                if uri not in uri_to_new:
                    uri_to_new[uri] = len(sources)
                    sources.append(uri)
                idx_map[orig_idx] = uri_to_new[uri]

        for sup in (getattr(gm, "grounding_supports", []) or []):
            seg = getattr(sup, "segment", None)
            old_idxs = (getattr(sup, "grounding_chunk_indices", []) or [])
            new_idxs, seen = [], set()
            for i in old_idxs:
                try:
                    k = int(i)
                    if k in idx_map:
                        mapped = idx_map[k]
                        if mapped not in seen:
                            seen.add(mapped)
                            new_idxs.append(mapped)
                except Exception:
                    continue

            supports.append({
                "grounding_chunk_indices": new_idxs,
                "text": getattr(seg, "text", "") if seg else "",
                "segment_end_index": int(getattr(seg, "end_index", 0) or 0) if seg else 0,
            })

        queries = [q for q in (getattr(gm, "retrieval_queries", []) or []) if q is not None]


    result = {
        "message": text,
        "sources": sources,
        "supports": supports,
        "queries": queries,
    }
    print(json.dumps(result, ensure_ascii=False), end="")


if __name__ == "__main__":
    generate()
