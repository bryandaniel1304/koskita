class Rule {
  final int id;
  final String name;

  Rule({required this.id, required this.name});

  factory Rule.fromJson(Map<String, dynamic> json) {
    return Rule(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
    };
  }
}
