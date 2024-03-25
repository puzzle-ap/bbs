create database php_jissen;

create table user(
    id int(11) primary key auto_increment,
    name varchar(255) not null,
    mail varchar(255) not null unique,
    password varchar(255) not null
);

create table post(
    user_id int(11) not null,
    title varchar(255) not null,
    comments varchar(255) not null,
    posted_at timestamp default current_timestamp() not null,
    foreign key (user_id) references user(id)
);

TRUNCATE TABLE user;
INSERT INTO user(name,mail,password) VALUES ('佐藤圭祐','satou@yahoo.com','$2y$10$vxyMjUxUyyEQiQd2SzLe.uqatkZ73oszQEVbY3GZj6Ai5XzCk/r3u');
INSERT INTO user(name,mail,password) VALUES ('鈴木順平','suzuki@yahoo.com','$2y$10$vxyMjUxUyyEQiQd2SzLe.uqatkZ73oszQEVbY3GZj6Ai5XzCk/r3u');
INSERT INTO user(name,mail,password) VALUES ('高橋隼人','takahashi@yahoo.com','$2y$10$vxyMjUxUyyEQiQd2SzLe.uqatkZ73oszQEVbY3GZj6Ai5XzCk/r3u');

alter table user add auto_login_token varchar(255) not null;

insert into post values ()

-- login.php
update user set auto_login_token = ? where id = ?;
select id, name, mail from user where auto_login_token = ?;

-- board.php
select count(user_id) from post;
select post.title, post.comments, posted_at, user.name from post join user on post.user_id = user.id order by posted_at desc limit 10 offset 0;