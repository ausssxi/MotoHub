import os
import logging
from datetime import datetime
from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, BigInteger, String, Numeric, Integer, Boolean, Text, JSON, DateTime, ForeignKey, UniqueConstraint
from sqlalchemy.orm import DeclarativeBase, sessionmaker

# 環境変数の読み込み
current_dir = os.path.dirname(os.path.abspath(__file__))
env_path = os.path.abspath(os.path.join(current_dir, "../../.env"))
load_dotenv(dotenv_path=env_path)

def get_db_url():
    user = os.getenv("DB_USERNAME")
    pw = os.getenv("DB_PASSWORD")
    host = os.getenv("DB_HOST", "db")
    port = os.getenv("DB_PORT", "3306")
    name = os.getenv("DB_DATABASE")
    return f"mysql+pymysql://{user}:{pw}@{host}:{port}/{name}"

# 接続設定の共通化
engine = create_engine(get_db_url(), pool_pre_ping=True, pool_size=10, max_overflow=20)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

class Base(DeclarativeBase):
    pass

# --- 共通モデル定義 ---

class Site(Base):
    __tablename__ = "sites"
    id = Column(BigInteger, primary_key=True)
    name = Column(String(50), unique=True)
    base_url = Column(String(255))

class Manufacturer(Base):
    __tablename__ = "manufacturers"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    name = Column(String(100), unique=True, nullable=False)
    country = Column(String(50))
    logo_url = Column(String(255))
    local_logo_path = Column(String(255))

class BikeModel(Base):
    __tablename__ = "bike_models"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    manufacturer_id = Column(BigInteger, ForeignKey("manufacturers.id"))
    name = Column(String(255), nullable=False, unique=True)
    category = Column(String(50))
    displacement = Column(Integer)
    image_url = Column(String(255))
    local_image_path = Column(JSON)

class Listing(Base):
    __tablename__ = "listings"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id"))
    shop_id = Column(BigInteger, ForeignKey("shops.id"))
    site_id = Column(BigInteger, ForeignKey("sites.id"))
    title = Column(String(255))
    source_url = Column(Text, nullable=False)
    price = Column(Numeric(12, 0))
    total_price = Column(Numeric(12, 0))
    model_year = Column(Integer)
    mileage = Column(Integer)
    image_urls = Column(JSON)
    local_image_paths = Column(JSON)
    has_repair_history = Column(Boolean, default=False)
    condition = Column(String(50))
    description = Column(Text)
    is_sold_out = Column(Boolean, default=False)
    needs_reindex = Column(Boolean, default=True)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

class Shop(Base):
    __tablename__ = "shops"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    prefecture = Column(String(20))
    address = Column(String(255))
    phone = Column(String(20))
    website_url = Column(Text)
    rating = Column(Numeric(3, 1), default=0.0)
    business_hours = Column(String(255))
    regular_holiday = Column(String(255))
    image_url = Column(String(255))
    local_image_path = Column(String(255))

class PriceHistory(Base):
    __tablename__ = "price_histories"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    listing_id = Column(BigInteger, ForeignKey("listings.id", ondelete="CASCADE"))
    old_price = Column(Integer, nullable=False)
    new_price = Column(Integer, nullable=False)
    is_notified = Column(Boolean, default=False)
    created_at = Column(DateTime, default=datetime.now)
    updated_at = Column(DateTime, default=datetime.now, onupdate=datetime.now)

class BikeModelIdentifier(Base):
    __tablename__ = "bike_model_identifiers"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    bike_model_id = Column(BigInteger, ForeignKey("bike_models.id", ondelete="CASCADE"))
    site_id = Column(BigInteger, ForeignKey("sites.id"))
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_site_identifier_uc'),)

class ShopIdentifier(Base):
    __tablename__ = "shop_identifiers"
    id = Column(BigInteger, primary_key=True, autoincrement=True)
    shop_id = Column(BigInteger, ForeignKey("shops.id", ondelete="CASCADE"))
    site_id = Column(BigInteger, ForeignKey("sites.id"))
    identifier = Column(String(100), nullable=False)
    __table_args__ = (UniqueConstraint('site_id', 'identifier', name='_shop_site_identifier_uc'),)