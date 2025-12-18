export default function ChatMessage({ role, content }) {
  return (
    <div className={`chat-msg ${role}`}>
      <div className="chat-bubble">{content}</div>
    </div>
  );
}
