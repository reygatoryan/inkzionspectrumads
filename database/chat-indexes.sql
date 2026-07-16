ALTER TABLE chat_messages ADD INDEX idx_chat_msgs_conv_read_sender (conversation_id, is_read, sender_id);
ALTER TABLE chat_messages ADD INDEX idx_chat_msgs_conv_id (conversation_id, id);
ALTER TABLE chat_conversations ADD INDEX idx_chat_conv_last_msg (last_message_at);
ALTER TABLE chat_conversations ADD INDEX idx_chat_conv_admin (admin_id);
ALTER TABLE chat_conversations ADD INDEX idx_chat_conv_user (user_id);
