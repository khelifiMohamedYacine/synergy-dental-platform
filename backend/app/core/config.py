"""Application settings, read from environment variables (see .env.example)."""

from functools import lru_cache

from pydantic import model_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

DEV_JWT_SECRET = "dev-only-insecure-secret-change-me-0123456789"  # noqa: S105
DEV_SERVICE_KEY = "dev-only-service-key"


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    api_env: str = "development"
    database_url: str = "postgresql+psycopg://synergy:synergy@localhost:5432/synergy"

    jwt_secret: str = DEV_JWT_SECRET
    jwt_access_ttl_minutes: int = 15
    jwt_refresh_ttl_days: int = 7
    cors_allowed_origins: str = ""
    service_api_key: str = DEV_SERVICE_KEY

    rate_limit_enabled: bool = True
    enquiry_rate_limit: str = "5/minute;30/day"
    login_rate_limit: str = "10/minute"

    seed_admin_email: str = "admin@example.test"
    seed_admin_password: str = ""

    wc_base_url: str = "http://wordpress"
    wc_consumer_key: str = ""
    wc_consumer_secret: str = ""

    @model_validator(mode="after")
    def _refuse_weak_secrets_in_production(self) -> "Settings":
        if self.api_env == "production":
            if self.jwt_secret == DEV_JWT_SECRET or len(self.jwt_secret) < 32:
                raise ValueError("JWT_SECRET must be set to a random value of 32+ characters.")
            if self.service_api_key == DEV_SERVICE_KEY or len(self.service_api_key) < 32:
                raise ValueError("SERVICE_API_KEY must be set to a random value of 32+ characters.")
        return self

    @property
    def is_production(self) -> bool:
        return self.api_env == "production"

    @property
    def cors_origins(self) -> list[str]:
        return [o.strip() for o in self.cors_allowed_origins.split(",") if o.strip()]


@lru_cache
def get_settings() -> Settings:
    return Settings()
