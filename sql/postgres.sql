CREATE SEQUENCE auth_tokens_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

CREATE TABLE auth_tokens (
	id integer DEFAULT nextval('auth_tokens_seq'::text) PRIMARY KEY,
        token varchar(128) DEFAULT '' NOT NULL,
        expires timestamp with time zone NOT NULL,      
        user_id integer NOT NULL
                REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
        user_name varchar(128) NOT NULL,
        user_pass varchar(128) NOT NULL,
        host varchar(255) NOT NULL
);
