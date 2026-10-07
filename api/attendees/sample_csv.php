<?php

require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sample_attendees.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['name', 'email', 'company', 'mobile', 'designation']);

fputcsv($output, ['Juan Cruz', 'juan.cruz@sap.com', 'SAP', '09171000001', 'IT Manager']);
fputcsv($output, ['Maria Santos', 'maria.santos@sap.com', 'SAP', '09171000002', 'Sales Director']);
fputcsv($output, ['Pedro Reyes', 'pedro.reyes@sap.com', 'SAP', '09171000003', 'Software Engineer']);
fputcsv($output, ['Ana Garcia', 'ana.garcia@sap.com', 'SAP', '09171000004', 'HR Specialist']);
fputcsv($output, ['Carlos Lopez', 'carlos.lopez@sap.com', 'SAP', '09171000005', 'Project Manager']);

fputcsv($output, ['Sofia Mendoza', 'sofia.m@delmonte.com', 'Delmonte', '09171000006', 'IT Manager']);
fputcsv($output, ['Miguel Torres', 'miguel.t@delmonte.com', 'Delmonte', '09171000007', 'Sales Director']);
fputcsv($output, ['Isabella Cruz', 'isabella.c@delmonte.com', 'Delmonte', '09171000008', 'Software Engineer']);
fputcsv($output, ['Diego Ramos', 'diego.r@delmonte.com', 'Delmonte', '09171000009', 'HR Specialist']);
fputcsv($output, ['Camila Flores', 'camila.f@delmonte.com', 'Delmonte', '09171000010', 'Project Manager']);

fputcsv($output, ['Lucas Martinez', 'lucas.m@amazon.com', 'Amazon', '09171000011', 'IT Manager']);
fputcsv($output, ['Valeria Diaz', 'valeria.d@amazon.com', 'Amazon', '09171000012', 'Sales Director']);
fputcsv($output, ['Mateo Gonzalez', 'mateo.g@amazon.com', 'Amazon', '09171000013', 'Software Engineer']);
fputcsv($output, ['Emma Rodriguez', 'emma.r@amazon.com', 'Amazon', '09171000014', 'HR Specialist']);
fputcsv($output, ['Sebastian Perez', 'sebastian.p@amazon.com', 'Amazon', '09171000015', 'Project Manager']);

fclose($output);
exit;
