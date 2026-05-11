-- Índices idempotentes compatíveis com MySQL/MariaDB (XAMPP)

DROP PROCEDURE IF EXISTS urban_create_index_if_missing;
DELIMITER //
CREATE PROCEDURE urban_create_index_if_missing(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_sql TEXT
)
BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*)
    INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name;

    IF v_exists = 0 THEN
        SET @urban_sql = p_index_sql;
        PREPARE urban_stmt FROM @urban_sql;
        EXECUTE urban_stmt;
        DEALLOCATE PREPARE urban_stmt;
    END IF;
END//
DELIMITER ;

CALL urban_create_index_if_missing('stop_times', 'idx_stop_times_trip_stop', 'CREATE INDEX idx_stop_times_trip_stop ON stop_times(trip_id, stop_sequence)');
CALL urban_create_index_if_missing('stop_times', 'idx_stop_times_stop_time', 'CREATE INDEX idx_stop_times_stop_time ON stop_times(stop_id, departure_time)');
CALL urban_create_index_if_missing('stop_times', 'idx_stop_times_departure_time', 'CREATE INDEX idx_stop_times_departure_time ON stop_times(departure_time)');
CALL urban_create_index_if_missing('stop_times', 'idx_stop_times_arrival_time', 'CREATE INDEX idx_stop_times_arrival_time ON stop_times(arrival_time)');
CALL urban_create_index_if_missing('stop_times', 'idx_stop_times_stop_depart_trip_seq', 'CREATE INDEX idx_stop_times_stop_depart_trip_seq ON stop_times(stop_id, departure_time, trip_id, stop_sequence)');
CALL urban_create_index_if_missing('stop_times', 'idx_stop_times_trip_stop_seq', 'CREATE INDEX idx_stop_times_trip_stop_seq ON stop_times(trip_id, stop_id, stop_sequence)');
CALL urban_create_index_if_missing('trips', 'idx_trips_route_service', 'CREATE INDEX idx_trips_route_service ON trips(route_id, service_id)');
CALL urban_create_index_if_missing('trips', 'idx_trips_service_id', 'CREATE INDEX idx_trips_service_id ON trips(service_id)');
CALL urban_create_index_if_missing('trips', 'idx_trips_route_id', 'CREATE INDEX idx_trips_route_id ON trips(route_id)');
CALL urban_create_index_if_missing('stops', 'idx_stops_location', 'CREATE INDEX idx_stops_location ON stops(stop_lat, stop_lon)');
CALL urban_create_index_if_missing('stops', 'idx_stops_name', 'CREATE INDEX idx_stops_name ON stops(stop_name)');
CALL urban_create_index_if_missing('routes', 'idx_routes_route_id', 'CREATE INDEX idx_routes_route_id ON routes(route_id)');
CALL urban_create_index_if_missing('routes', 'idx_routes_short_name', 'CREATE INDEX idx_routes_short_name ON routes(route_short_name)');
CALL urban_create_index_if_missing('calendar_dates', 'idx_calendar_dates_service', 'CREATE INDEX idx_calendar_dates_service ON calendar_dates(service_id, date)');
CALL urban_create_index_if_missing('calendar_dates', 'idx_calendar_dates_date', 'CREATE INDEX idx_calendar_dates_date ON calendar_dates(date)');
CALL urban_create_index_if_missing('favorite_routes', 'idx_favorite_routes_user_created', 'CREATE INDEX idx_favorite_routes_user_created ON favorite_routes(user_id, created_at)');
CALL urban_create_index_if_missing('search_history', 'idx_search_history_user_searched', 'CREATE INDEX idx_search_history_user_searched ON search_history(user_id, searched_at)');
CALL urban_create_index_if_missing('user_sessions', 'idx_user_sessions_expires', 'CREATE INDEX idx_user_sessions_expires ON user_sessions(expires_at)');
CALL urban_create_index_if_missing('route_cache', 'idx_route_cache_origin_dest_time', 'CREATE INDEX idx_route_cache_origin_dest_time ON route_cache(origin_lat, origin_lon, destination_lat, destination_lon, departure_time)');
CALL urban_create_index_if_missing('app_events', 'idx_app_events_type_created', 'CREATE INDEX idx_app_events_type_created ON app_events(event_type, created_at)');
CALL urban_create_index_if_missing('app_events', 'idx_app_events_user_created', 'CREATE INDEX idx_app_events_user_created ON app_events(user_id, created_at)');
CALL urban_create_index_if_missing('access_logs', 'idx_access_logs_path_created', 'CREATE INDEX idx_access_logs_path_created ON access_logs(path, created_at)');

DROP PROCEDURE IF EXISTS urban_create_index_if_missing;
