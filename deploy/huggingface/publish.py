"""Create or update the Hugging Face Space and its secrets, then upload the staged files.

Run by publish.ps1 inside a throwaway python container, with the staged Space in /space.
Environment: HF_TOKEN (write token), SPACE_NAME, WP_ADMIN_PASSWORD, SEED_ADMIN_PASSWORD.
"""

import os
import re

from huggingface_hub import HfApi

api = HfApi(token=os.environ["HF_TOKEN"])
owner = api.whoami()["name"]
name = os.environ.get("SPACE_NAME", "synergy-dental")
repo = f"{owner}/{name}"

api.create_repo(repo, repo_type="space", space_sdk="docker", exist_ok=True)
for key in ("WP_ADMIN_PASSWORD", "SEED_ADMIN_PASSWORD"):
    if os.environ.get(key):
        api.add_space_secret(repo, key, os.environ[key])

api.upload_folder(
    repo_id=repo,
    repo_type="space",
    folder_path="/space",
    commit_message=os.environ.get("COMMIT_MESSAGE", "Deploy from GitHub"),
    delete_patterns="*",  # remove files that are no longer part of the deployment
)

host = re.sub(r"[^a-z0-9]+", "-", f"{owner}-{name}".lower()).strip("-")
print(f"SPACE_PAGE=https://huggingface.co/spaces/{repo}")
print(f"SITE_URL=https://{host}.hf.space")
