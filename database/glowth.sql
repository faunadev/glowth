-- glowth database: run this file once (phpmyadmin > import, or mysql < glowth.sql)
create database if not exists glowth character set utf8mb4 collate utf8mb4_unicode_ci;
use glowth;

create table if not exists users (
  id int not null auto_increment primary key,
  name varchar(120) not null,
  email varchar(160) not null unique,
  phone varchar(30) not null default '',
  password_hash varchar(255) not null,
  role enum('customer','admin') not null default 'customer',
  is_active tinyint(1) not null default 1,
  created_at timestamp not null default current_timestamp
) engine=InnoDB default charset=utf8mb4;

create table if not exists products (
  id int not null auto_increment primary key,
  name varchar(160) not null,
  category enum('Makeup','Skincare') not null,
  sub_category varchar(40) not null,
  price decimal(10,2) not null,
  old_price decimal(10,2) null,
  image varchar(255) not null default '',
  description text not null,
  rating decimal(2,1) not null default 4.5,
  tag varchar(20) not null default '',
  stock int not null default 50,
  is_active tinyint(1) not null default 1,
  created_at timestamp not null default current_timestamp
) engine=InnoDB default charset=utf8mb4;

create table if not exists favorites (
  user_id int not null,
  product_id int not null,
  primary key (user_id, product_id),
  foreign key (user_id) references users(id) on delete cascade,
  foreign key (product_id) references products(id) on delete cascade
) engine=InnoDB default charset=utf8mb4;

create table if not exists coupons (
  id int not null auto_increment primary key,
  code varchar(40) not null unique,
  description varchar(160) not null default '',
  discount_type enum('percent','fixed') not null default 'percent',
  discount_value decimal(10,2) not null,
  min_spend decimal(10,2) not null default 0,
  max_uses int null,
  used_count int not null default 0,
  per_user_limit int not null default 1,
  new_user_only tinyint(1) not null default 0,
  assigned_user_id int null,
  starts_at datetime null,
  expires_at datetime null,
  is_active tinyint(1) not null default 1,
  created_at timestamp not null default current_timestamp,
  foreign key (assigned_user_id) references users(id) on delete cascade
) engine=InnoDB default charset=utf8mb4;

create table if not exists orders (
  id int not null auto_increment primary key,
  order_no varchar(30) not null unique,
  user_id int null,
  customer_name varchar(120) not null,
  email varchar(160) not null,
  phone varchar(30) not null,
  address varchar(255) not null,
  city varchar(80) not null,
  postal_code varchar(12) not null,
  payment_method enum('Cash on Delivery','GCash','Credit/Debit Card') not null,
  subtotal decimal(10,2) not null,
  discount decimal(10,2) not null default 0,
  shipping_fee decimal(10,2) not null default 0,
  total decimal(10,2) not null,
  coupon_code varchar(40) null,
  status enum('pending','processing','shipped','delivered','cancelled') not null default 'pending',
  created_at timestamp not null default current_timestamp,
  foreign key (user_id) references users(id) on delete set null
) engine=InnoDB default charset=utf8mb4;

create table if not exists order_items (
  id int not null auto_increment primary key,
  order_id int not null,
  product_id int null,
  product_name varchar(160) not null,
  price decimal(10,2) not null,
  quantity int not null,
  foreign key (order_id) references orders(id) on delete cascade,
  foreign key (product_id) references products(id) on delete set null
) engine=InnoDB default charset=utf8mb4;

create table if not exists coupon_redemptions (
  id int not null auto_increment primary key,
  coupon_id int not null,
  user_id int not null,
  order_id int not null,
  created_at timestamp not null default current_timestamp,
  foreign key (coupon_id) references coupons(id) on delete cascade,
  foreign key (user_id) references users(id) on delete cascade,
  foreign key (order_id) references orders(id) on delete cascade
) engine=InnoDB default charset=utf8mb4;

create table if not exists settings (
  key_name varchar(50) not null primary key,
  setting_value varchar(255) not null
) engine=InnoDB default charset=utf8mb4;

-- starting data
insert ignore into settings (key_name, setting_value) values
  ('shipping_fee', '99'),
  ('free_shipping_min', '1500'),
  ('welcome_percent', '15'),
  ('welcome_days', '30');

-- default admin login: admin@glowth.com / admin123 (change it after the first login)
insert ignore into users (id, name, email, phone, password_hash, role) values
  (1, 'GLOWTH Admin', 'admin@glowth.com', '', '$2y$10$0zN8J1wRi0SobMo0/zDaSOVz3.DWfQdeSQ6QxYD1jTLTlKU/FeY8C', 'admin');

insert ignore into coupons (id, code, description, discount_type, discount_value, min_spend, per_user_limit, new_user_only) values
  (1, 'FIRSTGLOW', '10 percent off your first order', 'percent', 10, 500, 1, 1);

insert ignore into products (id, name, category, sub_category, price, old_price, image, description, rating, tag, stock) values
  (1, 'Satin Skin Foundation', 'Makeup', 'Face', 899, null, 'https://images.unsplash.com/photo-1557205465-f3762edea6d3?auto=format&fit=crop&q=80&w=800', 'A lightweight, buildable liquid foundation with a soft satin finish. Blurs pores and evens tone for up to 24 hours without feeling heavy.', 4.8, 'Bestseller', 40),
  (2, 'Dewy Cushion Compact', 'Makeup', 'Face', 949, 1199, 'https://images.unsplash.com/photo-1531646317777-0619c7c5d1d3?auto=format&fit=crop&q=80&w=800', 'A refillable cushion compact with SPF 35 that gives a fresh, glass-like glow. Perfect for touch-ups on the go.', 4.7, '', 40),
  (3, 'Soft Focus Concealer', 'Makeup', 'Face', 549, null, 'https://images.unsplash.com/photo-1557205465-f3762edea6d3?auto=format&fit=crop&q=80&w=800', 'Creamy full-coverage concealer that brightens under-eyes and hides blemishes without creasing.', 4.6, '', 40),
  (4, 'Rosewater Lip Tint', 'Makeup', 'Lips', 399, null, 'https://images.unsplash.com/photo-1631214499500-2e34edcaccfe?auto=format&fit=crop&q=80&w=800', 'A water-based tint that stains lips a natural flush of pink. Light, comfortable and long-lasting.', 4.9, 'Bestseller', 40),
  (5, 'Cloud Glaze Lip Gloss', 'Makeup', 'Lips', 429, 549, 'https://images.unsplash.com/photo-1666594171486-858f82f5b191?auto=format&fit=crop&q=80&w=800', 'High-shine, non-sticky gloss enriched with squalane for plump, cushiony lips.', 4.5, '', 40),
  (6, 'Velvet Matte Lipstick', 'Makeup', 'Lips', 479, null, 'https://images.unsplash.com/photo-1555050455-f96634b5cba6?auto=format&fit=crop&q=80&w=800', 'Highly pigmented lipstick with a comfortable velvet finish that stays put through the day.', 4.8, 'New', 40),
  (7, 'Petal Pink Blush', 'Makeup', 'Face', 499, null, 'https://images.unsplash.com/photo-1503236823255-94609f598e71?auto=format&fit=crop&q=80&w=800', 'A silky powder blush that melts into skin for a soft, natural flush. Easy to build and blend.', 4.8, 'Bestseller', 40),
  (8, 'Lift & Define Mascara', 'Makeup', 'Eyes', 459, null, 'https://images.unsplash.com/photo-1670832209136-fad04d9920f9?auto=format&fit=crop&q=80&w=800', 'Smudge-proof, lengthening mascara with a flexible brush that separates and curls every lash.', 4.6, 'New', 40),
  (9, 'Precision Gel Eyeliner', 'Makeup', 'Eyes', 389, 499, 'https://images.unsplash.com/photo-1709477542170-f11ee7d471a0?auto=format&fit=crop&q=80&w=800', 'A waterproof gel eyeliner with a fine tip for crisp lines and a deep black finish.', 4.4, '', 40),
  (10, 'Rose Haze Eyeshadow Palette', 'Makeup', 'Eyes', 1199, null, 'https://images.unsplash.com/photo-1768983224486-b4dcd179b4a5?auto=format&fit=crop&q=80&w=800', 'Twelve blendable matte and shimmer shades in rosy neutrals, from soft daytime washes to evening drama.', 4.9, 'New', 40),
  (11, 'Gentle Gel Cleanser', 'Skincare', 'Cleanse', 459, 599, 'https://images.unsplash.com/photo-1748639320154-6ba118bccc74?auto=format&fit=crop&q=80&w=800', 'A pH-balanced cleanser that removes makeup and impurities without stripping the skin barrier.', 4.7, '', 40),
  (12, 'Barrier Repair Moisturizer', 'Skincare', 'Protect', 649, null, 'https://images.unsplash.com/photo-1764694071508-e4b1efcd39bc?auto=format&fit=crop&q=80&w=800', 'A rich yet fast-absorbing cream with ceramides and hyaluronic acid for 48-hour hydration.', 4.8, 'Bestseller', 40),
  (13, 'Vitamin C Glow Serum', 'Skincare', 'Treat', 799, null, 'https://images.unsplash.com/photo-1767256046031-743d33937c4e?auto=format&fit=crop&q=80&w=800', 'A brightening serum with 10% vitamin C and niacinamide to fade dark spots and boost radiance.', 4.9, 'Bestseller', 40),
  (14, 'Daily Shield Sunscreen SPF 50', 'Skincare', 'Protect', 599, null, 'https://images.unsplash.com/photo-1594055103006-7871176f1a7e?auto=format&fit=crop&q=80&w=800', 'Invisible, non-greasy sunscreen with broad-spectrum PA++++ protection. Wears well under makeup.', 4.7, 'New', 40),
  (15, 'Calming Rice Toner', 'Skincare', 'Cleanse', 499, 799, 'https://images.unsplash.com/photo-1605204350321-cfd0ac0702e1?auto=format&fit=crop&q=80&w=800', 'A soothing toner with rice extract that refines texture and preps skin for the next step.', 4.5, '', 40),
  (16, 'Overnight Hydration Mask', 'Skincare', 'Treat', 699, 899, 'https://images.unsplash.com/photo-1585945037805-5fd82c2e60b1?auto=format&fit=crop&q=80&w=800', 'A sleeping mask that locks in moisture overnight so you wake up plump and refreshed.', 4.6, '', 40),
  (17, 'Peptide Eye Cream', 'Skincare', 'Treat', 749, 989, 'https://images.unsplash.com/photo-1764694071508-e4b1efcd39bc?auto=format&fit=crop&q=80&w=800', 'A cooling eye cream with peptides and caffeine that reduces puffiness and fine lines.', 4.6, 'New', 40);
