DROP TABLE "LogParser";
DROP SEQUENCE "LogParser_Id_Seq";

CREATE TABLE "LogParser" (
    "Id" integer NOT NULL,
    "RawInput" json NOT NULL,
    "Format" text NOT NULL,
    "ParsedData" json NOT NULL,
    "Date" timestamp with time zone DEFAULT now()
);

CREATE FUNCTION retrieveparsedata(reqid integer) RETURNS "LogParser"
    LANGUAGE sql
    AS $$SELECT * FROM "LogParser" WHERE "Id" = reqid;$$;

CREATE FUNCTION storeparsedata(rawinput json, parseformat text, result json) RETURNS integer
    LANGUAGE sql
    AS $$INSERT INTO "LogParser" ("RawInput", "Format", "ParsedData") VALUES (rawinput, parseformat, result) RETURNING "Id";$$;

CREATE SEQUENCE "LogParser_Id_seq" START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;

ALTER SEQUENCE "LogParser_Id_seq" OWNED BY "LogParser"."Id";
ALTER TABLE ONLY "LogParser" ALTER COLUMN "Id" SET DEFAULT nextval('"LogParser_Id_seq"'::regclass);