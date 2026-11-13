# Group-1---ICS325
Group Project
# Scholarship Platform

## Team Members
- Eden Cass
- Chengjeng Lee
- Ismail Osman

## Project Description
A web application that helps students find scholarship opportunities.

## Technologies
- HTML
- CSS
- Bootstrap
- JavaScript
- PHP
- MySQL

## Installation

1. Clone repository
2. Import database schema. For an existing database, also run `scholarships/database/anonymous_search_logging.sql`.
3. Configure environment variables
4. Run application

## Repository Structure

## Data Quality Dashboard

Administrators can open **Admin → Data quality dashboard** to review scholarships with deadlines before today, missing or more than 180-day-old verification dates, missing titles/providers/official URLs, and possible duplicates. Duplicate candidates share a provider and a title after trimming whitespace and ignoring letter case. The dashboard identifies candidates for human review and does not change or remove records.

## Accounts and access

Visitors can register as a Student or Parent using the registration page. Passwords
are hashed with PHP's `password_hash()`. Log in to start a session; sessions rotate
their ID on login and expire after the configured `SESSION_TIMEOUT` of inactivity.
Admin, import, and data-quality pages require an Administrator or Super-admin role.

## Branching Strategy

main
develop
feature/*
bugfix/*
